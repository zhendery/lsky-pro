<?php

/**
 * 参考EasyImage版进行修改  https://github.com/icret/EasyImages2.0/blob/master/app/compress/Imagick/class.Imgcompress.php
 * InterventionImage提供的压缩又耗时，压缩效果又一般。而且所谓的格式转换只是改了个后缀，实际转换后的文件看文件头还是原格式，怪不得大小压不下来。
 * 而官方自带的GD库压缩效果很好，压缩速度也很快（目前只支持静态图）。
 */

namespace App\Services;

class ImageCompressService
{
    private $src;
    private $dst;
    private $image;
    private $imageinfo;
    private $percent = 50;

    /**
     * 图片压缩
     * @param string $src 源图
     * @param string $dst 目标格式
     * @param float $percent 压缩比例
     */
    public function __construct($src, $dst, $percent = 100)
    {
        $this->src = $src;
        $this->dst = $dst;
        $this->percent = $percent;
    }

    private function _openImage()
    {
        list($width, $height, $type, $attr) = getimagesize($this->src);
        $this->imageinfo = array(
            'width' => $width,
            'height' => $height,
            'type' => image_type_to_extension($type, false),
            'attr' => $attr,
        );
        $fun = "imagecreatefrom" . $this->imageinfo['type'];
        $this->image = $fun($this->src);
    }

    /**
     * GD库提供的图片转换，带压缩，效果一般但速度较快，实测画质损失肉眼几乎不可见，压缩比不是很稳定。
     * @param string $src 源图
     * @param float $percent 压缩比例
     */
    public function compressImgHigh()
    {
        $this->_openImage();

        if (in_array($this->imageinfo['type'], ['png', 'webp'])){
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
        }

        $funcs = "image" . ($this->dst ?: $this->imageinfo['type']);
        $funcs($this->image, $this->src, $this->percent);
    }

    /**
     * 低质量图片压缩，实测画质损失肉眼可见但不太大，压缩速度较慢，但压缩比巨高几乎能达到1/10~1/20。
     * @param string $src 源图
     * @param float $percent 压缩比例
     */
    public function compressImgLow()
    {
        // 先压一遍
        $this->compressImgHigh();

        $new_width = $this->imageinfo['width'] * $this->percent / 100;
        $new_height = $this->imageinfo['height'] * $this->percent / 100;
        $image_thump = imagecreatetruecolor($new_width, $new_height);
        /**
         * 保留图片透明通道
         * 简单图床 EasyImage 2.0 2021-5-9 21:48:59
         * 参考: https://www.imooc.com/wenda/detail/581249
         */
        if (in_array($this->imageinfo['type'], ['png', 'webp'])){
            imagealphablending($image_thump, false);
            imagesavealpha($image_thump, true);
        }

        //将原图复制带图片载体上面，并且按照一定比例压缩,极大的保持了清晰度
        imagecopyresampled($image_thump, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->imageinfo['width'], $this->imageinfo['height']);

        imagedestroy($this->image);
        $this->image = $image_thump;
        
        
        $funcs = "image" . ($this->dst ?: $this->imageinfo['type']);
        $funcs($this->image, $this->src);
    }

    /**
     * 销毁图片
     */
    public function __destruct()
    {
        imagedestroy($this->image);
    }
}
