
/**
 * Neon Beats - Core Game Logic
 * 包含音频同步、音符生成、判定系统和渲染循环
 */

// --- 配置常量 ---
const CONFIG = {
    TRACK_COUNT: 4,
    NOTE_SPEED: 600, // 像素/秒
    JUDGMENT_LINE_Y_PERCENT: 0.85, // 判定线在屏幕高度的位置 (0-1)
    AUDIO_OFFSET: 0, // 音频延迟补偿(ms)
    KEYS: ['d', 'f', 'j', 'k'],
    JUDGMENT_WINDOWS: {
        PERFECT: 50, // ms
        GREAT: 100,
        GOOD: 150,
        MISS: 200
    },
    SCORES: {
        PERFECT: 1000,
        GREAT: 800,
        GOOD: 500,
        MISS: 0
    }
};

// --- 游戏状态管理 ---
const GameState = {
    isPlaying: false,
    startTime: 0,
    audioContext: null,
    audioBuffer: null,
    sourceNode: null,
    notes: [], // 当前屏幕上的音符对象
    score: 0,
    combo: 0,
    maxCombo: 0,
    stats: { perfect: 0, great: 0, good: 0, miss: 0 },
    totalNotes: 0,
    processedNotes: new Set() // 记录已处理的音符ID，防止重复判定
};

// --- DOM 元素引用 ---
const DOM = {
    trackArea: document.getElementById('track-area'),
    notesLayer: document.getElementById('notes-layer'),
    keys: document.querySelectorAll('.key'),
    score: document.getElementById('score-display'),
    accuracy: document.getElementById('accuracy-display'),
    comboContainer: document.getElementById('combo-container'),
    comboCount: document.getElementById('combo-count'),
    judgeFeedback: document.getElementById('judge-feedback'),
    startMenu: document.getElementById('start-menu'),
    resultScreen: document.getElementById('result-screen'),
    finalScore: document.getElementById('final-score'),
    finalCombo: document.getElementById('final-combo'),
    finalAccuracy: document.getElementById('final-accuracy'),
    countPerfect: document.getElementById('count-perfect'),
    countMiss: document.getElementById('count-miss'),
    startBtn: document.getElementById('start-btn'),
    restartBtn: document.getElementById('restart-btn')
};

// --- 音频系统 ---
class AudioSystem {
    constructor() {
        this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    }

    async loadMusic(url) {
        try {
            const response = await fetch(url);
            const arrayBuffer = await response.arrayBuffer();
            return await this.ctx.decodeAudioData(arrayBuffer);
        } catch (e) {
            console.error("Audio load failed, using synthetic beat", e);
            return null;
        }
    }

    play(buffer) {
        if (this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
        
        const source = this.ctx.createBufferSource();
        source.buffer = buffer;
        source.connect(this.ctx.destination);
        
        // 精确调度播放
        const now = this.ctx.currentTime;
        source.start(now + 0.1); // 稍微延迟以准备
        
        GameState.sourceNode = source;
        GameState.startTime = now + 0.1;
        
        source.onended = () => {
            endGame();
        };
    }

    getCurrentTime() {
        if (!GameState.isPlaying) return 0;
        return (this.ctx.currentTime - GameState.startTime) * 1000; // ms
    }
    
    // 生成简单的合成音效作为反馈
    playHitSound(type) {
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        
        if (type === 'perfect') {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, this.ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(110, this.ctx.currentTime + 0.1);
        } else if (type === 'miss') {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(150, this.ctx.currentTime);
            osc.frequency.linearRampToValueAtTime(100, this.ctx.currentTime + 0.1);
        } else {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(440, this.ctx.currentTime);
        }
        
        gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.1);
        
        osc.start();
        osc.stop(this.ctx.currentTime + 0.1);
    }
}

const audioSys = new AudioSystem();

// --- 谱面数据生成器 (模拟) ---
function generateChart(durationSeconds) {
    const chart = [];
    const bpm = 128;
    const beatInterval = 60000 / bpm; // ms per beat
    let time = 1000; // Start after 1s
    
    while (time < durationSeconds * 1000) {
        // 随机生成音符
        if (Math.random() > 0.3) {
            const lane = Math.floor(Math.random() * 4);
            chart.push({
                id: time + lane, // Unique ID
                time: time,
                lane: lane,
                type: 'tap',
                hit: false
            });
        }
        
        // 偶尔生成双押
        if (Math.random() > 0.9) {
             const lane1 = Math.floor(Math.random() * 4);
             let lane2 = Math.floor(Math.random() * 4);
             while(lane1 === lane2) lane2 = Math.floor(Math.random() * 4);
             
             chart.push({
                id: time + lane2 + 10,
                time: time,
                lane: lane2,
                type: 'tap',
                hit: false
            });
        }

        time += beatInterval / 2; // Half beats
    }
    return chart.sort((a, b) => a.time - b.time);
}

let currentChart = [];

// --- 游戏逻辑 ---

function initGame() {
    // 绑定事件
    DOM.startBtn.addEventListener('click', startGame);
    DOM.restartBtn.addEventListener('click', resetGame);
    
    // 键盘输入
    window.addEventListener('keydown', handleInput);
    window.addEventListener('keyup', handleKeyUp);
    
    // 触摸输入
    DOM.keys.forEach(key => {
        key.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const lane = parseInt(key.dataset.key);
            triggerLane(lane, true);
        });
        key.addEventListener('touchend', (e) => {
            e.preventDefault();
            const lane = parseInt(key.dataset.key);
            triggerLane(lane, false);
        });
        // 鼠标点击支持
        key.addEventListener('mousedown', () => {
             const lane = parseInt(key.dataset.key);
             triggerLane(lane, true);
        });
        key.addEventListener('mouseup', () => {
             const lane = parseInt(key.dataset.key);
             triggerLane(lane, false);
        });
    });
}

async function startGame() {
    DOM.startMenu.classList.add('hidden');
    DOM.resultScreen.classList.add('hidden');
    
    // 重置状态
    GameState.score = 0;
    GameState.combo = 0;
    GameState.maxCombo = 0;
    GameState.stats = { perfect: 0, great: 0, good: 0, miss: 0 };
    GameState.processedNotes.clear();
    GameState.notes = [];
    DOM.notesLayer.innerHTML = '';
    updateHUD();
    
    // 生成谱面 (假设歌曲长度30秒用于演示，实际应加载真实音频)
    // 为了演示效果，我们使用一个合成的音频缓冲区或者简单的定时器模拟
    // 这里为了代码独立性，不依赖外部MP3文件，使用 Oscillator 模拟节奏背景音
    createBackgroundBeat();
    
    currentChart = generateChart(30); // 30 seconds song
    GameState.totalNotes = currentChart.length;
    GameState.isPlaying = true;
    
    // 启动渲染循环
    requestAnimationFrame(gameLoop);
}

function createBackgroundBeat() {
    // 简单的节拍器声音，让玩家有节奏感
    const ctx = audioSys.ctx;
    const nextNoteTime = ctx.currentTime + 0.1;
    GameState.startTime = ctx.currentTime + 0.1;
    
    // 由于没有真实音频文件，我们用代码生成一个简单的循环
    // 在实际项目中，这里会替换为 audioSys.play(buffer)
    
    // 模拟播放结束
    setTimeout(() => {
        if(GameState.isPlaying) endGame();
    }, 30000);
}

function gameLoop() {
    if (!GameState.isPlaying) return;
    
    const currentTime = audioSys.getCurrentTime();
    
    // 1. 生成音符 DOM
    // 查找即将进入屏幕的音符 (提前2秒生成)
    const spawnWindow = 2000; 
    // 简单优化：只遍历未生成的音符。实际项目可用索引指针
    currentChart.forEach(note => {
        if (!note.element && note.time - currentTime < spawnWindow && note.time > currentTime - 500) {
            spawnNoteElement(note);
        }
    });
    
    // 2. 更新音符位置
    const trackHeight = DOM.trackArea.clientHeight;
    const judgmentLineY = trackHeight * CONFIG.JUDGMENT_LINE_Y_PERCENT;
    
    GameState.notes.forEach((noteObj, index) => {
        if (noteObj.hit) return;
        
        // 计算位置: 距离 = 速度 * 时间差
        // 当 time == currentTime 时，应该在判定线
        const timeDiff = noteObj.data.time - currentTime;
        const pixelOffset = (timeDiff / 1000) * CONFIG.NOTE_SPEED;
        
        // 顶部位置 = 判定线位置 - 偏移量
        const topPos = judgmentLineY - pixelOffset;
        
        noteObj.element.style.top = `${topPos}px`;
        
        // Miss 检测: 如果超过判定线太多且未击中
        if (timeDiff < -CONFIG.JUDGMENT_WINDOWS.MISS && !noteObj.hit) {
            registerHit(noteObj, 'miss');
        }
        
        // 清理超出屏幕的音符
        if (topPos > trackHeight) {
            if (noteObj.element.parentNode) {
                noteObj.element.remove();
            }
            GameState.notes.splice(index, 1);
        }
    });
    
    requestAnimationFrame(gameLoop);
}

function spawnNoteElement(noteData) {
    const el = document.createElement('div');
    el.className = 'note';
    // 计算左位置 (25% per lane)
    el.style.left = `${noteData.lane * 25 + 1}%`; // +1% for margin
    el.style.top = '-50px'; // Start above
    
    DOM.notesLayer.appendChild(el);
    
    GameState.notes.push({
        data: noteData,
        element: el,
        hit: false
    });
    noteData.element = el; // Link back
}

function handleInput(e) {
    if (!GameState.isPlaying) return;
    if (e.repeat) return;
    
    const keyIndex = CONFIG.KEYS.indexOf(e.key.toLowerCase());
    if (keyIndex !== -1) {
        triggerLane(keyIndex, true);
    }
}

function handleKeyUp(e) {
    const keyIndex = CONFIG.KEYS.indexOf(e.key.toLowerCase());
    if (keyIndex !== -1) {
        triggerLane(keyIndex, false);
    }
}

function triggerLane(laneIndex, isDown) {
    // 视觉反馈
    const keyEl = DOM.keys[laneIndex];
    if (isDown) {
        keyEl.classList.add('active');
        checkHit(laneIndex);
    } else {
        keyEl.classList.remove('active');
    }
}

function checkHit(laneIndex) {
    const currentTime = audioSys.getCurrentTime();
    
    // 寻找该轨道上最接近判定线的未击中音符
    // 过滤出该轨道的音符
    const laneNotes = GameState.notes.filter(n => n.data.lane === laneIndex && !n.hit);
    
    if (laneNotes.length === 0) return;
    
    // 找到时间差最小的
    let bestNote = null;
    let minDiff = Infinity;
    
    laneNotes.forEach(n => {
        const diff = Math.abs(n.data.time - currentTime);
        if (diff < minDiff) {
            minDiff = diff;
            bestNote = n;
        }
    });
    
    if (bestNote && minDiff <= CONFIG.JUDGMENT_WINDOWS.MISS) {
        // 判定
        let judgment = 'miss';
        if (minDiff <= CONFIG.JUDGMENT_WINDOWS.PERFECT) judgment = 'perfect';
        else if (minDiff <= CONFIG.JUDGMENT_WINDOWS.GREAT) judgment = 'great';
        else if (minDiff <= CONFIG.JUDGMENT_WINDOWS.GOOD) judgment = 'good';
        
        registerHit(bestNote, judgment);
    }
}

function registerHit(noteObj, judgment) {
    if (noteObj.hit) return; // Prevent double hit
    noteObj.hit = true;
    
    // 视觉移除
    if (noteObj.element) {
        noteObj.element.style.opacity = '0';
        setTimeout(() => {
            if(noteObj.element.parentNode) noteObj.element.remove();
        }, 100);
    }
    
    // 更新数据
    GameState.stats[judgment]++;
    
    if (judgment === 'miss') {
        GameState.combo = 0;
        audioSys.playHitSound('miss');
    } else {
        GameState.combo++;
        if (GameState.combo > GameState.maxCombo) GameState.maxCombo = GameState.combo;
        
        // 分数计算
        let baseScore = CONFIG.SCORES[judgment.toUpperCase()];
        // 连击加成
        let comboMultiplier = 1 + (GameState.combo / 100);
        GameState.score += Math.floor(baseScore * comboMultiplier);
        
        audioSys.playHitSound(judgment);
    }
    
    updateHUD();
    showJudgeText(judgment);
}

function updateHUD() {
    DOM.score.innerText = GameState.score.toString().padStart(6, '0');
    
    // 计算准确率
    const totalHits = GameState.stats.perfect + GameState.stats.great + GameState.stats.good + GameState.stats.miss;
    let acc = 0;
    if (totalHits > 0) {
        // 简单加权计算
        const weightedScore = (GameState.stats.perfect * 100) + (GameState.stats.great * 80) + (GameState.stats.good * 50);
        const maxPossible = totalHits * 100;
        acc = (weightedScore / maxPossible) * 100;
    } else {
        acc = 100;
    }
    DOM.accuracy.innerText = acc.toFixed(2) + '%';
    
    // Combo
    DOM.comboCount.innerText = GameState.combo;
    if (GameState.combo > 0) {
        DOM.comboContainer.classList.add('show');
    } else {
        DOM.comboContainer.classList.remove('show');
    }
}

function showJudgeText(text) {
    const el = DOM.judgeFeedback;
    el.innerText = text.toUpperCase();
    
    // 颜色
    if (text === 'perfect') el.style.color = '#38bdf8'; // Cyan
    else if (text === 'great') el.style.color = '#a78bfa'; // Purple
    else if (text === 'good') el.style.color = '#facc15'; // Yellow
    else el.style.color = '#ef4444'; // Red
    
    // 重置动画
    el.classList.remove('animate-pop');
    void el.offsetWidth; // Trigger reflow
    el.classList.add('animate-pop');
}

function endGame() {
    GameState.isPlaying = false;
    DOM.resultScreen.classList.remove('hidden');
    
    DOM.finalScore.innerText = GameState.score;
    DOM.finalCombo.innerText = GameState.maxCombo;
    DOM.countPerfect.innerText = GameState.stats.perfect;
    DOM.countMiss.innerText = GameState.stats.miss;
    
    // 重新计算最终准确率
    const totalHits = GameState.stats.perfect + GameState.stats.great + GameState.stats.good + GameState.stats.miss;
    let acc = 0;
    if (totalHits > 0) {
        const weightedScore = (GameState.stats.perfect * 100) + (GameState.stats.great * 80) + (GameState.stats.good * 50);
        const maxPossible = totalHits * 100;
        acc = (weightedScore / maxPossible) * 100;
    }
    DOM.finalAccuracy.innerText = acc.toFixed(2) + '%';
}

function resetGame() {
    DOM.resultScreen.classList.add('hidden');
    startGame();
}

// 初始化
initGame();

