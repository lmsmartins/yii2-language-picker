<?php

namespace lmsmartins\languagepicker\widgets;

use Yii;
use yii\helpers\Html;
use yii\web\View;

class LanguagePicker extends \yii\base\Widget
{
    const SKIN_DROPDOWN = 'dropdown';
    const SIZE_SMALL = 'small';
    const SIZE_LARGE = 'large';

    private $_SKINS = [
        self::SKIN_DROPDOWN => [
            'languageItemTemplate' => '<li><a class="option-language-picker" data-hidden="{languageKey}" title="{language}"><span class="flag-icon flag-icon-{language}"></span> {name}</a></li>',
            'languageActiveItemTemplate' => '
                <button class="btn btn-default dropdown-toggle btn-custom-dropdown" type="button" id="picker-selected-language" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                    <span class="flag-icon flag-icon-{language}"></span>
                    {name}
                    <span class="caret"></span>
                </button>',
            'currencyItemTemplate' => '<li><a class="option-currency-picker" data-hidden="{currencyKey}" title="{name}"><span class="currency-key-container">{currencyKey}</span><span class="currency-name-container">{name}</span></a></li>',
            'currencyActiveItemTemplate' => '
                <button class="btn btn-default dropdown-toggle btn-custom-dropdown" type="button" id="picker-selected-currency" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                    <span class="currency-key-container">{currencyKey}</span>
                    <span class="currency-name-container">{name}</span>
                    <span class="caret"></span>
                </button>',
            'parentTemplate' => '
                <div id="language-currency-modal" class="modal fade" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content modal-language-currency">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="row-fluid">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label>Language</label>
                                            <div class="dropdown language-picker-container"> {languageActiveItem}
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenu1">
                                                    {languageItems}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label>Currency</label>
                                            <div class="dropdown currency-picker-container"> {currencyActiveItem}
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenu1">
                                                    {currencyItems}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default btn-form modal-btn-cancel" data-dismiss="modal">Cancel</button>
                                <form action="" method="get">
                                    <input type="hidden" id="language-picker-hidden" name="language-picker-hidden" value="">
                                    <input type="hidden" id="currency-picker-hidden" name="currency-picker-hidden" value="">
                                    <button type="submit" class="btn btn-primary btn-form modal-btn-save"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Save</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>',
        ],
    ];

    private $_SIZES = [
        self::SIZE_SMALL => 'pickuz\languagepicker\bundles\LanguageSmallIconsAsset',
        self::SIZE_LARGE => 'pickuz\languagepicker\bundles\LanguageLargeIconsAsset',
    ];

    public $skin;
    public $size;
    public $parentTemplate;
    public $languageItemTemplate;
    public $currencyItemTemplate;
    public $languageActiveItemTemplate;
    public $currencyActiveItemTemplate;
    public $languageAsset;
    public $languages;
    public $currencies;
    public $languagesCurrenciesAssignment;
    public $encodeLabels = true;

    public static function widget($config = array())
    {
        if (empty($config['languages']) || !is_array($config['languages'])) {
            $config['languages'] = Yii::$app->languagepicker->languages;
        }

        if (empty($config['currencies']) || !is_array($config['currencies'])) {
            $config['currencies'] = Yii::$app->languagepicker->currencies;
        }

        if (empty($config['languagesCurrenciesAssignment']) || !is_array($config['languagesCurrenciesAssignment'])) {
            $config['languagesCurrenciesAssignment'] = Yii::$app->languagepicker->languagesCurrenciesAssignment;
        }

        return parent::widget($config);
    }

    public function init()
    {
        $this->initSkin();
        parent::init();
    }

    public function run()
    {
        $isInteger = is_integer(key($this->languages));
        if ($isInteger) {
            $this->languages = array_flip($this->languages);
        }
        $languagePicker = $this->renderDropdown($isInteger);
        echo $languagePicker;
    }

    private function renderDropdown($isInteger)
    {
        $languageItems = '';
        $languageActiveItem = '';
        foreach ($this->languages as $language => $name) {
            $name = $isInteger ? '' : $name;
            if (Yii::$app->language == $language) {
                $languageActiveItem = $this->renderLanguageItem($language, $name, $this->languageActiveItemTemplate);
            }
            $languageItems .= $this->renderLanguageItem($language, $name, $this->languageItemTemplate);
        }

        $currencyItems = '';
        $currencyActiveItem = '';
        foreach ($this->currencies as $currency => $name) {
            $name = $isInteger ? '' : $name;
            if (Yii::$app->session->get('user.currency') == $currency) {
                $currencyActiveItem = $this->renderCurrencyItem($currency, $name, $this->currencyActiveItemTemplate);
            }
            $currencyItems .= $this->renderCurrencyItem($currency, $name, $this->currencyItemTemplate);
        }

        $this->registerAssets();
        return strtr($this->parentTemplate, [
                '{languageActiveItem}' => $languageActiveItem,
                '{languageItems}' => $languageItems,
                '{size}' => $this->size,
                '{currencyActiveItem}' => $currencyActiveItem,
                '{currencyItems}' => $currencyItems
            ]
        );
    }

    private function initSkin()
    {
        if ($this->skin && empty($this->_SKINS[$this->skin])) {
            throw new \yii\base\InvalidConfigException('The skin does not exist: ' . $this->skin);
        }
        if ($this->size && empty($this->_SIZES[$this->size])) {
            throw new \yii\base\InvalidConfigException('The size does not exist: ' . $this->size);
        }
        if ($this->skin) {
            foreach ($this->_SKINS[$this->skin] as $property => $value) {
                if (!$this->$property) {
                    $this->$property = $value;
                }
            }
        }
        if ($this->size) {
            $this->languageAsset = $this->_SIZES[$this->size];
        }
    }

    private function registerAssets()
    {
        $view = $this->getView();
        $view->registerJs("
            $('.option-language-picker').on('click touch', function () {
                var languagesCurrenciesAssignment = " . json_encode($this->languagesCurrenciesAssignment) . "
                var selectedLanguageKey = $(this).data('hidden');
                $('#language-picker-hidden').val(selectedLanguageKey);
                $('#picker-selected-language').html($(this).html()+'<span class=\"caret\"></span>');
                $('#picker-selected-currency').html('<span class=\"currency-key-container\">'+languagesCurrenciesAssignment[selectedLanguageKey]['key']+'</span><span class=\"currency-name-container\">' + languagesCurrenciesAssignment[selectedLanguageKey]['currency'] + '</span><span class=\"caret\"></span>');
                $('#currency-picker-hidden').val(languagesCurrenciesAssignment[selectedLanguageKey]['key']);
            });
            $('.option-currency-picker').on('click touch', function () {
                $('#currency-picker-hidden').val($(this).data('hidden'));
                $('#picker-selected-currency').html($(this).html()+'<span class=\"caret\"></span>');
            });
        ",
            View::POS_END, 'language-currency-picker-js'
        );
    }

    protected function renderLanguageItem($language, $name, $template)
    {
        if ($this->encodeLabels) {
            $language = Html::encode($language);
            $name = Html::encode($name);
        }
        $params = array_merge([''], Yii::$app->request->queryParams, ['language-picker-language' => $language]);
        //For languages like pt-PT, pt-BR or en-US, en-UK
        $languageArray = explode("-", $language);
        return strtr($template, [
            '{languageKey}' => $params['language-picker-language'],
            '{name}' => $name,
            '{language}' => isset($languageArray[1]) ? strtolower($languageArray[1]) : strtolower($languageArray[0])
        ]);
    }

    protected function renderCurrencyItem($currency, $name, $template)
    {

        if ($this->encodeLabels) {
            $currency = Html::encode($currency);
            $name = Html::encode($name);
        }

        $params = array_merge([''], Yii::$app->request->queryParams, ['currency-picker-currency' => $currency]);
        //For currencies like pt-PT, pt-BR or en-US, en-UK
        return strtr($template, [
            '{currencyKey}' => $params['currency-picker-currency'],
            '{name}' => $name,
        ]);
    }
}
