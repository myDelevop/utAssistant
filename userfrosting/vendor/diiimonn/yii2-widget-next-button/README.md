# yii2-widget-next-button
Widget render wrap container for a list with next button.

## Installation

To install with composer:

```
$ php composer.phar require diiimonn/yii2-widget-next-button "dev-master"
```

or add

```
"diiimonn/yii2-widget-next-button": "dev-master"
```

to the ```require``` section of your `composer.json` file.

## Usage

### controller:

```php
...
use yii\data\Pagination;
...

class SiteController extends Controller
{
    ...
    public function actionIndex($id)
    {
        ...
        $query = MyModel::find();

        $queryCount = clone $query;

        $pagination = new Pagination([
            'totalCount' => $queryCount->count(),
            'pageSize' => 10,
            'page' => 0,
        ]);

        $isNext = $pagination->getPageCount() > 1;

        $query->offset($pagination->offset);
        $query->limit($pagination->limit);
        $models = $query->all();

        ...

        return $this->render('index', [
            ...
            'models' => $models,
            'isNext' => $isNext,
        ]);
    }

    ...

    public function actionNext($id, $page = 0)
    {
        Yii::$app->response->format = 'json';

        $json = new \stdClass();

        $query = MyModel::find();

        $queryCount = clone $query;

        $pagination = new Pagination([
            'totalCount' => $queryCount->count(),
            'pageSize' => 10,
            'page' => $page,
        ]);

        $nextPage = $page + 1;

        $json->page = $pagination->getPageCount() > $nextPage ? $nextPage : 0;

        $query->offset($pagination->offset);
        $query->limit($pagination->limit);
        $models = $query->all();

        ...

        $json->html = $this->renderPartial('_items', [
            ...
            'models' => $models,
        ]);

        return $json;
    }

    ...
}
```

### structure views:

```
├── site
│   ├── index.php
│   ├── _items.php
│   ├── ...

```

### view index.php

```php
...
use diiimonn\widgets\NextButton;
use yii\helpers\Url;

...

<?php $nextButton = NextButton::begin([
    'buttonContent' => Yii::t('app', 'Show next ...'),
    'buttonOptions' => [/* button tag options */],
    'isNext' => $isNext,
    'scriptOptions' => [
        'ajax' => [
            'url' => Url::toRoute(['next', /* other params */]), // Url for ajax request to actionNext in SiteController. Required parameter
        ],
    ],
    'options' => [/* widget tag options */],
    'containerOptions' => [/* container tag for items options */],
    'buttonContainerOptions' => [],
    'buttonBlockOptions' => [], // for example ['class' => 'row']
    'buttonWrapOptions' => [], // for example ['class' => 'col-md-6 col-md-offset-3']
]) ?>

<?= $this->render('_items', [
    ...
    'models' => $models,
]) ?>

<?php $nextButton::end() ?>
...
```