<?php

namespace iAvatar777\widgets\FileUpload8;

use cs\Application;
use cs\services\File;
use cs\services\SitePath;

use Imagine\Image\Box;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\httpclient\Client;
use yii\widgets\InputWidget;
use yii\web\UploadedFile;
use yii\imagine\Image;
use Imagine\Image\ManipulatorInterface;
use cs\base\BaseForm;
use cs\services\UploadFolderDispatcher;

/**
 * Используется для загрузки картинок в облако, обрезки, маркировки
 */
class FileUpload extends InputWidget
{
    /** с обрезкой, по умолчанию */
    const MODE_THUMBNAIL_CUT = ManipulatorInterface::THUMBNAIL_OUTBOUND;

    /** вписать */
    const MODE_THUMBNAIL_FIELDS = ManipulatorInterface::THUMBNAIL_INSET;

    /** вписать с фоном */
    const MODE_THUMBNAIL_WHITE = 'white';

    /** @var  array
     * - maxSize - int - кол-во КБ
     * - allowedExtensions - Array - массив рисширений которые можно грузить, по умолчанию ['jpg', 'jpeg', 'png']
     * - data - Array - массив для отправки постом при загрузке
     * - controller - string - идентификатор контроллера
     * - server - string - адрес сервера для загрузки
     */
    public $settings;

    /** @var  array массив на обработку изображения */
    public $update;

    /** @var  array массив событий */
    public $events;

    public function init()
    {
        if (is_null($this->value)) {
            $attrubute = $this->attribute;
            $this->value = $this->model->$attrubute;
        }
        if (is_null($this->settings)) {
            $this->settings = [];
        }
    }

    /**
     * Renders the widget.
     */
    public function run()
    {
        $this->registerClientScript();
        $html = [];
        $html[] = Html::input('button', null, 'Выбери файл', [
            "class" => "btn btn-primary btn-large clearfix upload-btn buttonDragAndDrop",
        ]);
        $maxSize = ArrayHelper::getValue($this->settings, 'maxSize', 1000);
        $allowedExtensions = ArrayHelper::getValue($this->settings, 'allowedExtensions', []);
        $s = join(',', $allowedExtensions) . ' ' . '(' . $maxSize . ' Кб макс)';
        $html[] = Html::tag('span', Html::tag('i', $s), [
            "style" => "padding-left:5px;vertical-align:middle;",
        ]);
        $html[] = Html::tag('div', null, [
            "class" => "clearfix redtext errormsg",
            "style" => "padding-top: 10px;",
        ]);
        $html[] = Html::hiddenInput(Html::getInputName($this->model, $this->attribute), $this->value, ['class' => 'inputValue']);

        $html[] = Html::tag('div', null, [
            "class" => "progress-wrap pic-progress-wrap",
            "style" => "margin-top:10px;margin-bottom:10px;",
        ]);

        $v = null;
        if (!Application::isEmpty($this->value)) {
            $v = '<img src="' . $this->value . '" style="width:100%;max-width:200px">';
        }
        $html[] = Html::tag('div', $v, [
            "data-id" => "picture",
            "class"   => "clear picbox",
            "style"   => "padding-top:0px;padding-bottom:10px;width:100%;max-width:200px",
        ]);

        $html[] = Html::tag('div', null, [
            "class" => "clear-line",
            "style" => "margin-top:10px;",
        ]);

        return Html::tag('div', join('', $html), ['class' => 'content-box', 'id' => $this->id]);
    }

    /**
     * Registers the needed JavaScript.
     */
    public function registerClientScript()
    {
        $options = [
            'selector'  => '#' . $this->id,
            'server'    => $this->getServer(),
        ];
        if ($this->update) {
            $options['data']['update'] = Json::encode($this->update);
        }

        $options['controller'] = ArrayHelper::getValue($this->settings, 'controller', 'upload');
        $options = ArrayHelper::merge($options, $this->settings);
        $jsonOptions = Json::encode($options);
        $this->getView()->registerJs("FileUpload8.init({$jsonOptions});");
        Asset::register($this->getView());
    }

    /**
     * Returns the options for the captcha JS widget.
     *
     * @return array the options
     */
    protected function getClientOptions()
    {
        return [];
    }

    /**
     * Выдает сервер для загрузки файла
     *
     * @return string например https://cloud1.i-am-avatar.com
     */
    public function getServer()
    {
        if (isset($this->settings['server'])) {
            return $this->settings['server'];
        }

        return '';
    }

    /**
     * Выдает имя файла в зависимости от инлекса
     * Например если имя файла в параметре value = https://cloud1.i-am-avatar.com/15523/12918_dsffesasd.jpg
     * а параметр $index = 'crop'
     * то выдано будет  https://cloud1.i-am-avatar.com/15523/12918_dsffesasd_crop.jpg
     * То есть по сути идет лишь добавление к конец файла суфикса со знаком подчеркивания
     *
     * @param string $file
     * @param string $index
     *
     * @return string
     */
    public static function getFile($file, $index)
    {
        $info = pathinfo($file);
        $ext = $info['extension'];

        return $info['dirname'] . '/' . $info['filename'] . '_' . $index . '.' . $ext;
    }


    /**
     * @param array $field
     *
     * @return bool
     */
    public function onDelete($field)
    {
        $fieldName = $field[BaseForm::POS_DB_NAME];
        $value = $this->model->$fieldName;

        if (isset($this->events['onDelete'])) {
            $eventFunction = $this->events['onDelete'];
            $eventFunction($this->model);
        }

        if (!is_null($value)) {
            if ($value != '') {
                $url = new \cs\services\Url($value);
                /** @var \common\services\AvatarCloud $cloud */
                $cloud = Yii::$app->AvatarCloud;
                $indexList = [];
                foreach ($this->update as $u) {
                    $indexList[] = $u['index'];
                }
                $response = $cloud->_post($url->scheme . '://' . $url->host, 'upload/file-upload8-delete', [
                    'file'      => $value,
                    'indexList' => $indexList,
                ]);
                if ($response->statusCode != 200) {
                    Yii::warning(VarDumper::dumpAsString($response), 'avatar\common\widgets\FileUpload8\FileUpload::onDelete');
                }
            }
        }


        return true;
    }


    /**
     * Сохраняет картинку по формату
     *
     * @param \cs\services\File $file
     * @param \cs\services\SitePath $destination
     * @param array $field
     * @param array | false $format => [
     *                              3000,
     *                              3000,
     *                              FileUpload::MODE_THUMBNAIL_OUTBOUND
     *                              'isExpandSmall' => true,
     *                              ] ,
     *
     * @return \cs\services\SitePath
     */
    public static function saveImage($file, $destination, $format)
    {
        if ($format === false || is_null($format)) {
            $file->save($destination->getPathFull());
            return $destination;
        }

        $widthFormat = 1;
        $heightFormat = 1;
        if (is_numeric($format)) {
            // Обрезать квадрат
            $widthFormat = $format;
            $heightFormat = $format;
        } else if (is_array($format)) {
            $widthFormat = $format[0];
            $heightFormat = $format[1];
        }

        // generate a thumbnail image
        $mode = ArrayHelper::getValue($format, 2, self::MODE_THUMBNAIL_CUT);
        if ($file->isContent()) {
            $image = Image::getImagine()->load($file->content);
        } else {
            $image = Image::getImagine()->open($file->path);
        }
        if (ArrayHelper::getValue($format, 'isExpandSmall', true)) {
            $image = self::expandImage($image, $widthFormat, $heightFormat, $mode);
        }
        $quality = ArrayHelper::getValue($format, 'quality', 80);
        $options = ['quality' => $quality];
        if (in_array($mode, [self::MODE_THUMBNAIL_CUT, self::MODE_THUMBNAIL_FIELDS])) {
            $image->thumbnail(new Box($widthFormat, $heightFormat), $mode)->save($destination->getPathFull(), $options);
        } else {
            // получаю картинку под заданный прямоугольник
            $image->thumbnail(new Box($widthFormat, $heightFormat), self::MODE_THUMBNAIL_FIELDS);

            // формирую белый прямоугольник с заданными параметрами $widthFormat*$heightFormat
            $palette = new \Imagine\Image\Palette\RGB();
            $color = $palette->color('#fff', 0);
            $imageWhite = Image::getImagine()->create(new Box($widthFormat, $heightFormat), $color);

            // накладываю уменьшенную картинку на белый прямоугольник
            $result = Image::watermark($imageWhite, $image , [0, (int) (($heightFormat - $image->getSize()->getHeight()) / 2)]);
            $ext = strtolower($destination->getExtension());
            $format = 'jpg';
            if (in_array($ext, ['jpg', 'jpeg'])) $format = 'jpg';
            if (in_array($ext, ['png'])) $format = 'png';
            file_put_contents($destination->getPathFull(), $result->get($format));
        }

        return $destination;
    }

    /**
     * Расширяет маленькую картинку по заданной стратегии
     *
     * @param \Imagine\Image\ImageInterface $image
     * @param int $widthFormat
     * @param int $heightFormat
     * @param int $mode
     *
     * @return \Imagine\Image\ImageInterface
     */
    protected static function expandImage($image, $widthFormat, $heightFormat, $mode)
    {
        $size = $image->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();
        if ($width < $widthFormat || $height < $heightFormat) {
            // расширяю картинку
            if ($mode == self::MODE_THUMBNAIL_CUT) {
                if ($width < $widthFormat && $height >= $heightFormat) {
                    $size = $size->widen($widthFormat);
                } else if ($width >= $widthFormat && $height < $heightFormat) {
                    $size = $size->heighten($heightFormat);
                } else if ($width < $widthFormat && $height < $heightFormat) {
                    // определяю как расширять по ширине или по высоте
                    if ($width / $widthFormat < $height / $heightFormat) {
                        $size = $size->widen($widthFormat);
                    } else {
                        $size = $size->heighten($heightFormat);
                    }
                }
                $image->resize($size);
            } else {
                if ($width < $widthFormat && $height >= $heightFormat) {
                    $size = $size->heighten($heightFormat);
                } else if ($width >= $widthFormat && $height < $heightFormat) {
                    $size = $size->widen($widthFormat);
                } else if ($width < $widthFormat && $height < $heightFormat) {
                    // определяю как расширять по ширине или по высоте
                    if ($width / $widthFormat < $height / $heightFormat) {
                        $size = $size->heighten($heightFormat);
                    } else {
                        $size = $size->widen($widthFormat);
                    }
                }
                $image->resize($size);
            }
        }

        return $image;
    }


    /**
     * @param array $field
     *
     * @return bool
     */
    public function onUpdate($field)
    {
        return true;
    }

    /**
     * @param array $field
     *
     * @return bool
     */
    public function onInsert($field)
    {
        return true;
    }

}
