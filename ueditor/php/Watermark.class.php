<?php
/**
 * 水印处理类
 * 用于为上传的图片添加水印
 * 
 * @author UEditor Plus
 * @version 1.0
 */

class Watermark
{
    // 水印文字
    private $text = 'blacktor';

    // 水印透明度 (0-100)
    private $opacity;

    // 文字字体大小 (GD 内置字体 1-5)
    private $font = 5;

    // 右下角边距
    private $margin = 10;

    // 支持的图片类型
    private $supportedTypes = array('image/jpeg', 'image/png', 'image/gif');
    
    /**
     * 构造函数
     * 
     * @param string $watermarkPath 兼容参数（保留但不使用）
     * @param int $opacity 透明度 (0-100)
     */
    public function __construct($watermarkPath = null, $opacity = 50)
    {
        $this->opacity = max(0, min(100, intval($opacity)));
    }
    
    /**
     * 为图片添加水印
     * 
     * @param string $imagePath 原图片路径
     * @return bool 是否成功
     */
    public function addWatermark($imagePath)
    {
        // 检查文件是否存在
        if (!file_exists($imagePath)) {
            return false;
        }
        
        // 获取图片信息
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }
        
        // 检查是否支持的类型
        if (!in_array($imageInfo['mime'], $this->supportedTypes)) {
            return false;
        }
        
        $textMetrics = $this->getTextMetrics();

        // 图片太小不添加水印（宽或高不够显示文字+边距）
        if (
            $imageInfo[0] < $textMetrics['width'] + $this->margin * 2 ||
            $imageInfo[1] < $textMetrics['height'] + $this->margin * 2
        ) {
            return true; // 跳过但返回成功
        }
        
        // 创建图片资源
        $image = $this->createImageResource($imagePath, $imageInfo['mime']);

        if ($image === false) {
            return false;
        }

        $this->applyTextWatermark($image, $imageInfo, $textMetrics);
        
        // 保存图片
        $saved = $this->saveImage($image, $imagePath, $imageInfo['mime']);
        
        // 释放资源
        imagedestroy($image);
        
        return $saved;
    }
    
    /**
     * 创建图片资源
     * 
     * @param string $path 图片路径
     * @param string $mime MIME类型
     * @return resource|false
     */
    private function createImageResource($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                $img = @imagecreatefrompng($path);
                if ($img) {
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                }
                return $img;
            case 'image/gif':
                return @imagecreatefromgif($path);
            default:
                return false;
        }
    }
    
    /**
     * 计算文字尺寸
     *
     * @return array
     */
    private function getTextMetrics()
    {
        $width = imagefontwidth($this->font) * strlen($this->text);
        $height = imagefontheight($this->font);

        return array('width' => $width, 'height' => $height);
    }

    /**
     * 添加文字水印（暗+明）
     *
     * @param resource $image 图片资源
     * @param array $imageInfo 原图信息
     * @param array $textMetrics 文字尺寸
     */
    private function applyTextWatermark($image, $imageInfo, $textMetrics)
    {
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $alpha = $this->getAlphaValue();
        $light = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, $alpha);

        $x = $imageInfo[0] - $textMetrics['width'] - $this->margin;
        $y = $imageInfo[1] - $textMetrics['height'] - $this->margin;

        // 暗水印作为阴影，明水印覆盖文字
        imagestring($image, $this->font, $x + 1, $y + 1, $this->text, $dark);
        imagestring($image, $this->font, $x, $y, $this->text, $light);
    }

    /**
     * 透明度转换（0-100 -> 127-0）
     *
     * @return int
     */
    private function getAlphaValue()
    {
        $alpha = 127 - (int) round(127 * ($this->opacity / 100));
        if ($alpha < 0) {
            return 0;
        }
        if ($alpha > 127) {
            return 127;
        }
        return $alpha;
    }
    
    /**
     * 保存图片
     * 
     * @param resource $image 图片资源
     * @param string $path 保存路径
     * @param string $mime MIME类型
     * @return bool
     */
    private function saveImage($image, $path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagejpeg($image, $path, 90);
            case 'image/png':
                return @imagepng($image, $path, 9);
            case 'image/gif':
                return @imagegif($image, $path);
            default:
                return false;
        }
    }
}
