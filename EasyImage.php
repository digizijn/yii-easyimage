<?php
/**
 * EasyImage class file.
 * @author Artur Zhdanov <zhdanovartur@gmail.com>
 * @copyright Copyright &copy; Artur Zhdanov 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version 1.0.6
 */

Yii::setPathOfAlias('easyimage', dirname(__FILE__));
Yii::import('easyimage.drivers.*');

class EasyImage extends CApplicationComponent
{
    /**
     * Resizing directions
     */
    const RESIZE_NONE = 0x01;
    const RESIZE_WIDTH = 0x02;
    const RESIZE_HEIGHT = 0x03;
    const RESIZE_AUTO = 0x04;
    const RESIZE_INVERSE = 0x05;
    const RESIZE_PRECISE = 0x06;

    /**
     * Flipping directions
     */
    const FLIP_HORIZONTAL = 0x11;
    const FLIP_VERTICAL = 0x12;

    /**
     * @var object Image
     */
    private $_image;

    /**
     * @var string driver type: GD, Imagick
     */
    public $driver = 'GD';

    /**
     * @var string relative path where the cache files are kept
     */
    public $cachePath = '/assets/easyimage/';

    /**
     * @var int cache lifetime in seconds
     */
    public $cacheTime = 2592000;

    /**
     * @var int value of quality: 0-100 (only for JPEG)
     */
    public $quality = 100;

    /**
     * @var bool use retina-resolutions
     * This setting increases the load on the server.
     */
    public $retinaSupport = false;

    /**
     * @var int Permissions for main cache directory and subdirectories.
     */
    public $newDirMode = 0775;

    /**
     * @var int Permissions for cached files.
     */
    public $newFileMode = 0660;

    /**
     * Constructor.
     * @param string $file
     * @param string $driver
     */
    public function __construct($file = null, $driver = null)
    {
        if (is_file($file)) {
            return $this->_image = Image::factory($this->detectPath($file), $driver ? $driver : $this->driver);
        }
    }

    /**
     * Convert object to binary data of current image.
     * Must be rendered with the appropriate Content-Type header or it will not be displayed correctly.
     * @return string as binary
     */
    public function __toString()
    {
        try {
            return $this->image()->render();
        } catch (CException $e) {
            return '';
        }
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        // Publish "retina.js" library (http://retinajs.com/)
        if ($this->retinaSupport) {
            Yii::app()->clientScript->registerScriptFile(
                Yii::app()->assetManager->publish(
                    Yii::getPathOfAlias('easyimage.assets') . '/retina.min.js'
                ),
                CClientScript::POS_END
            );
        }
    }

    /**
     * This method returns the current Image instance.
     * @return Image
     * @throws CException
     */
    public function image()
    {
        if ($this->_image instanceof Image) {
            return $this->_image;
        } else {
            throw new CException('Don\'t have image');
        }
    }

    /**
     * This method detects which (absolute or relative) path is used.
     * @param array $file path
     * @return string path
     */
    public function detectPath($file)
    {
        $fullPath = dirname(Yii::app()->basePath) . $file;
        if (is_file($fullPath)) {
            return $fullPath;
        }
        return $file;
    }

    /**
     * Performance of image manipulation and save result.
     * @param string $file the path to the original image
     * @param string $newFile path to the resulting image
     * @param array $params
     * @return bool operation status
     * @throws CException
     */
    private function _doThumbOf($file, $newFile, $params)
    {
        if ($file instanceof Image) {
            $this->_image = $file;
        } else {
            if (!is_file($file)) {
                return false;
            }
            $this->_image = Image::factory($this->detectPath($file), $this->driver);
        }
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'resize':
                    $this->resize(
                        isset($value['width']) ? $value['width'] : null,
                        isset($value['height']) ? $value['height'] : null,
                        isset($value['master']) ? $value['master'] : null
                    );
                    break;
                case 'crop':
                    if (!isset($value['width']) || !isset($value['height'])) {
                        throw new CException('Params "width" and "height" is required for action "' . $key . '"');
                    }
                    $this->crop(
                        $value['width'],
                        $value['height'],
                        isset($value['offset_x']) ? $value['offset_x'] : null,
                        isset($value['offset_y']) ? $value['offset_y'] : null
                    );
                    break;
                case 'scaleAndCrop':
                    $this->scaleAndCrop($value['width'], $value['height']);
                    break;
                case 'rotate':
                    if (is_array($value)) {
                        if (!isset($value['degrees'])) {
                            throw new CException('Param "degrees" is required for action "' . $key . '"');
                        }
                        $this->rotate($value['degrees']);
                    } else {
                        $this->rotate($value);
                    }
                    break;
                case 'flip':
                    if (is_array($value)) {
                        if (!isset($value['direction'])) {
                            throw new CException('Param "direction" is required for action "' . $key . '"');
                        }
                        $this->flip($value['direction']);
                    } else {
                        $this->flip($value);
                    }
                    break;
                case 'sharpen':
                    if (is_array($value)) {
                        if (!isset($value['amount'])) {
                            throw new CException('Param "amount" is required for action "' . $key . '"');
                        }
                        $this->sharpen($value['amount']);
                    } else {
                        $this->sharpen($value);
                    }
                    break;
                case 'reflection':
                    $this->reflection(
                        isset($value['height']) ? $value['height'] : null,
                        isset($value['opacity']) ? $value['opacity'] : 100,
                        isset($value['fade_in']) ? $value['fade_in'] : false
                    );
                    break;
                case 'watermark':
                    if (is_array($value)) {
                        $this->watermark(
                            isset($value['watermark']) ? $value['watermark'] : null,
                            isset($value['offset_x']) ? $value['offset_x'] : null,
                            isset($value['offset_y']) ? $value['offset_y'] : null,
                            isset($value['opacity']) ? $value['opacity'] : 100
                        );
                    } else {
                        $this->watermark($value);
                    }
                    break;
                case 'background':
                    if (is_array($value)) {
                        if (!isset($value['color'])) {
                            throw new CException('Param "color" is required for action "' . $key . '"');
                        }
                        $this->background(
                            $value['color'],
                            isset($value['opacity']) ? $value['opacity'] : 100
                        );
                    } else {
                        $this->background($value);
                    }
                    break;
                case 'quality':
                    if (!isset($value)) {
                        throw new CException('Param "' . $key . '" can\'t be empty');
                    }
                    $this->quality = $value;
                    break;
                case 'type':
                    break;
                default:
                    throw new CException('Action "' . $key . '" is not found');
            }
        }
        $result = $this->save($newFile, $this->quality);
        @chmod($newFile, $this->newFileMode);
        return $result;
    }

    /**
     * This method returns the URL to the cached thumbnail.
     * @param string $file path
     * @param array $params
     * @param mixed $hash cache version modifier
     * @return string URL path
     */
    public function thumbSrcOf($file, $params = array(), $hash = null)
    {
        // Paths
        $hash = md5($file . serialize($params) . (string)$hash);
        $cachePath = Yii::getpathOfAlias('webroot') . $this->cachePath . $hash[0];
        $cacheFileExt = isset($params['type']) ? $params['type'] : pathinfo($file, PATHINFO_EXTENSION);
        $cacheFileName = $hash . '.' . $cacheFileExt;
        $cacheFile = $cachePath . DIRECTORY_SEPARATOR . $cacheFileName;
        $webCacheFile = Yii::app()->baseUrl . $this->cachePath . $hash[0] . '/' . $cacheFileName;

        // Return URL to the cache image
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTime)) {
            return $webCacheFile;
        }

        // Make cache dir
        if (!is_dir($cachePath)) {
            mkdir($cachePath, $this->newDirMode, true);
            chmod(Yii::getpathOfAlias('webroot') . $this->cachePath, $this->newDirMode);
            chmod($cachePath, $this->newDirMode);
        }

        // Create and caching thumbnail use params
        if (!is_file($file)) {
            return false;
        }
        $image = Image::factory($this->detectPath($file), $this->driver);
        $originWidth = $image->width;
        $originHeight = $image->height;
        $result = $this->_doThumbOf($image, $cacheFile, $params);
        unset($image);

        // Same for high-resolution image
        if ($this->retinaSupport && $result) {
            if ($this->image()->width * 2 <= $originWidth && $this->image()->height * 2 <= $originHeight) {
                $retinaFile = $cachePath . DIRECTORY_SEPARATOR . $hash . '@2x.' . $cacheFileExt;
                if (isset($params['resize']['width']) && isset($params['resize']['height'])) {
                    $params['resize']['width'] = $this->image()->width * 2;
                    $params['resize']['height'] = $this->image()->height * 2;
                }
                $this->_doThumbOf($file, $retinaFile, $params);
            }
        }

        return $webCacheFile;
    }

    /**
     * This method returns prepared HTML code for cached thumbnail.
     * Use standard yii-component CHtml::image().
     * @param string $file path
     * @param array $params
     * @param array $htmlOptions
     * @param mixed $hash cache version modifier
     * @return string HTML
     */
    public function thumbOf($file, $params = array(), $htmlOptions = array(), $hash = null)
    {
        return CHtml::image(
            $this->thumbSrcOf($file, $params, $hash),
            isset($htmlOptions['alt']) ? $htmlOptions['alt'] : '',
            $htmlOptions
        );
    }

    /**
     * Description of the methods for the AutoComplete feature in a IDE
     * because it uses a design pattern "factory".
     */

    public function resize($width = null, $height = null, $master = null)
    {
        return $this->image()->resize($width, $height, $master);
    }

    public function crop($width, $height, $offset_x = null, $offset_y = null)
    {
        return $this->image()->crop($width, $height, $offset_x, $offset_y);
    }

    public function scaleAndCrop($width, $height)
    {
        $this->resize(
            $width,
            $height,
            self::RESIZE_INVERSE
        );
        $this->crop($width, $height);
    }

    public function rotate($degrees)
    {
        return $this->image()->rotate($degrees);
    }

    public function flip($direction)
    {
        return $this->image()->flip($direction);
    }

    public function sharpen($amount)
    {
        return $this->image()->sharpen($amount);
    }

    public function reflection($height = null, $opacity = 100, $fade_in = false)
    {
        return $this->image()->reflection($height, $opacity, $fade_in);
    }

    public function watermark($watermark, $offset_x = null, $offset_y = null, $opacity = 100)
    {
        if ($watermark instanceof EasyImage) {
            $watermark = $watermark->image();
        } elseif (is_string($watermark)) {
            $watermark = Image::factory(Yii::getpathOfAlias('webroot') . $watermark);
        }
        return $this->image()->watermark($watermark, $offset_x, $offset_y, $opacity);
    }

    public function background($color, $opacity = 100)
    {
        return $this->image()->background($color, $opacity);
    }

    public function save($file = null, $quality = 100)
    {
        return $this->image()->save($file, $quality);
    }

    public function render($type = null, $quality = 100)
    {
        return $this->image()->render($type, $quality);
    }
}
