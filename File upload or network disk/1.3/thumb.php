<?php
/**
 * PHP 生成缩略图并直接输出到浏览器
 * 用法: <img src="thumb.php?src=original.jpg&w=200&h=200">
 */

// 1. 获取参数并验证
$sourcePath = isset($_GET['src']) ? $_GET['src'] : '';
$maxWidth   = isset($_GET['w']) ? intval($_GET['w']) : 200;
$maxHeight  = isset($_GET['h']) ? intval($_GET['h']) : 200;

// 安全校验：防止路径遍历攻击
if (empty($sourcePath) || !file_exists($sourcePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('Image not found');
}

// 2. 获取原图信息
$imgInfo = getimagesize($sourcePath);
if ($imgInfo === false) {
    exit('Invalid image');
}

$origWidth  = $imgInfo[0];
$origHeight = $imgInfo[1];
$origType   = $imgInfo[2]; // IMAGETYPE_JPEG, IMAGETYPE_PNG, etc.

// 3. 计算等比缩放尺寸
$ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
// 如果原图比目标小，是否放大？通常建议不放大以保持清晰，这里假设不放大
if ($ratio >= 1) {
    $newWidth  = $origWidth;
    $newHeight = $origHeight;
} else {
    $newWidth  = (int)round($origWidth * $ratio);
    $newHeight = (int)round($origHeight * $ratio);
}

// 4. 创建原图资源
switch ($origType) {
    case IMAGETYPE_JPEG:
        $srcImg = imagecreatefromjpeg($sourcePath);
        break;
    case IMAGETYPE_PNG:
        $srcImg = imagecreatefrompng($sourcePath);
        break;
    case IMAGETYPE_GIF:
        $srcImg = imagecreatefromgif($sourcePath);
        break;
    default:
        exit('Unsupported format');
}

// 5. 创建目标画布
$thumbImg = imagecreatetruecolor($newWidth, $newHeight);

// 6. 处理 PNG 透明度（关键步骤）
if ($origType == IMAGETYPE_PNG) {
    imagealphablending($thumbImg, false);
    imagesavealpha($thumbImg, true);
    $transparent = imagecolorallocatealpha($thumbImg, 255, 255, 255, 127);
    imagefilledrectangle($thumbImg, 0, 0, $newWidth, $newHeight, $transparent);
}

// 7. 重采样缩放（高质量）
imagecopyresampled(
    $thumbImg, $srcImg,
    0, 0, 0, 0,
    $newWidth, $newHeight,
    $origWidth, $origHeight
);

// 8. 设置 Header 并输出
// 注意：在此之前不能有任何 echo、print 或空格输出
switch ($origType) {
    case IMAGETYPE_JPEG:
        header('Content-Type: image/jpeg');
        // 第二个参数为 null 表示直接输出到浏览器，85 为质量
        imagejpeg($thumbImg, null, 85);
        break;
    case IMAGETYPE_PNG:
        header('Content-Type: image/png');
        // 第二个参数为 null 表示直接输出
        imagepng($thumbImg, null);
        break;
    case IMAGETYPE_GIF:
        header('Content-Type: image/gif');
        imagegif($thumbImg, null);
        break;
}

// 9. 释放内存
imagedestroy($srcImg);
imagedestroy($thumbImg);
exit;
?>
