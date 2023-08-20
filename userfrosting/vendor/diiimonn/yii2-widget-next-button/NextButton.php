<?php
namespace diiimonn\widgets;

use Yii;
use yii\helpers\Html;
use yii\base\Widget;
use yii\helpers\Json;
use yii\base\InvalidConfigException;

/**
 * Class NextButton
 * @package diiimonn\widgets
 */
class NextButton extends Widget
{
    public $options = [];

    public $containerOptions = [];

    public $buttonOptions = [];

    public static $buttonContainerOptions = [];
    public static $buttonBlockOptions = [];
    public static $buttonWrapOptions = [];
    public $buttonContent = '';

    public $scriptOptions = [];

    public $isNext = true;

    /**
     * @var \yii\web\AssetBundle
     */
    protected $asset;

    public function init()
    {
        parent::init();

        if (!isset($this->scriptOptions['ajax']['url'])) {
            throw new InvalidConfigException('jQuery ajax url must by specified.');
        }

        $this->registerAsset();
        $this->registerClientScript();

        if (empty($this->buttonContent)) {
            $this->buttonContent = Yii::t('app', 'Show next ...');
        }

        $this->options['id'] = $this->id;
        Html::addCssClass($this->options, 'nb__container');

        echo Html::beginTag('div', $this->options);

        Html::addCssClass($this->containerOptions, 'nb__items');

        echo Html::beginTag('div', $this->containerOptions);
    }

    public function run()
    {
        Html::addCssClass($this->buttonOptions, 'nb__button');

        return $this->isNext ? Html::a($this->buttonContent, '#', $this->buttonOptions) : '';
    }

    public static function end()
    {
        echo Html::endTag('div');

        Html::addCssClass(static::$buttonContainerOptions, 'nb__button_container');

        echo Html::beginTag('div', static::$buttonContainerOptions);

        echo Html::beginTag('div', static::$buttonBlockOptions);
        echo Html::beginTag('div', static::$buttonWrapOptions);

        $widget = parent::end();

        echo Html::endTag('div');
        echo Html::endTag('div');
        echo Html::endTag('div');
        echo Html::endTag('div');

        return $widget;
    }

    protected function registerClientScript()
    {
        $view = $this->getView();
        $script = '$(".nb__button", $("#' . $this->id . '")).nextButton(' . Json::encode($this->scriptOptions) . ');';
        $view->registerJs($script, $view::POS_END);
    }

    protected function registerAsset()
    {
        $this->asset = NextButtonAsset::register($this->getView());
    }
}
