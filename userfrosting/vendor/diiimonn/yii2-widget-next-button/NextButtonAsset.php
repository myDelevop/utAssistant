<?php
namespace diiimonn\widgets;

use yii\web\AssetBundle;

/**
 * Class NextButtonAsset
 * @package diiimonn\widgets
 */
class NextButtonAsset extends AssetBundle
{
    public $sourcePath = '@vendor/diiimonn/yii2-widget-next-button/assets';

    public $depends = [
        'yii\web\JqueryAsset'
    ];

    public $js = [
        'js/next.button.js'
    ];
}
