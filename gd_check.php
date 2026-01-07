<?php
header('Content-Type: text/plain');
echo "Server GD check (via web)\n";
echo "PHP: " . PHP_VERSION . "\n";
if (!extension_loaded('gd')) {
    echo "GD extension: NOT LOADED\n";
    exit(0);
}
$info = function_exists('gd_info') ? gd_info() : [];
echo "GD extension: LOADED\n";
echo "GD Version: " . ($info['GD Version'] ?? 'unknown') . "\n";
echo "WebP support: " . ((isset($info['WebP Support']) && $info['WebP Support']) ? 'YES' : 'NO') . "\n";
echo "JPEG support: " . ((isset($info['JPEG Support']) && $info['JPEG Support']) ? 'YES' : 'NO') . "\n";
echo "PNG support: " . ((isset($info['PNG Support']) && $info['PNG Support']) ? 'YES' : 'NO') . "\n";
echo "imagewebp(): " . (function_exists('imagewebp') ? 'AVAILABLE' : 'MISSING') . "\n";
echo "imagejpeg(): " . (function_exists('imagejpeg') ? 'AVAILABLE' : 'MISSING') . "\n";
echo "imagecreatefrompng(): " . (function_exists('imagecreatefrompng') ? 'AVAILABLE' : 'MISSING') . "\n";
?>