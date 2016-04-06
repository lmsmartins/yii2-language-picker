<?php

namespace lmsmartins\languagepicker;

use Yii;

/**
 * Component.
 *
 * Examples:
 *
 * Minimal code:
 *
 * ~~~
 * 'language' => 'en',
 * 'bootstrap' => ['languagepicker'],
 * 'components' => [
 *      'languagepicker' => [
 *          'class' => 'pickuz\languagepicker\Component',
 *          'languages' => ['en', 'de', 'fr']               // List of available languages
 *      ]
 * ],
 * ~~~
 *
 * Complete example:
 *
 * ~~~
 * 'language' => 'en-US',
 * 'bootstrap' => ['languagepicker'],
 * 'components' => [
 *      'languagepicker' => [
 *          'class' => 'pickuz\languagepicker\Component',
 *          'languages' => ['en-US', 'de-DE', 'fr-FR'],     // List of available languages
 *          'cookieName' => 'language',                     // Name of the cookie.
 *          'cookieDomain' => 'example.com',                // Domain of the cookie.
 *          'expireDays' => 64,                             // The expiration time of the cookie is 64 days.
 *          'callback' => function() {
 *              if (!\Yii::$app->user->isGuest) {
 *                  $user = User::findOne(\Yii::$app->user->id);
 *                  $user->language = \Yii::$app->language;
 *                  $user->save();
 *              }
 *          }
 *      ]
 * ]
 */
class Component extends \yii\base\Component
{

    /**
     * @var function - function to execute after changing the language of the site.
     */
    public $callback;

    /**
     * @var integer expiration date of the cookie storing the language of the site.
     */
    public $expireDays = 40;

    public $languageCookieName = 'language';

    public $currencyCookieName = 'currency';

    /**
     * @var string The domain that the language cookie is available to.
     * For details see the $domain parameter description of PHP setcookie() function.
     */
    public $cookieDomain = '';
//    public $cookieDomain = 'pickuz.com';

    /**
     * @var array List of available languages
     *  Formats supported in the pre-defined skins:
     *
     * ~~~
     *  ['en', 'de', 'es']
     *  ['en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français']
     *  ['en-US', 'de-DE', 'fr-FR']
     *  ['en-US' => 'English', 'de-DE' => 'Deutsch', 'fr-FR' => 'Français']
     * ~~~
     *
     */
    public $languages;
    public $languagesTextOnly;
    public $currencies;
    public $languagesCurrenciesAssignment;
    public $defaultCurrency;

    public function __construct($config = array())
    {
        if (empty($config['languages'])) {
            throw new \yii\base\InvalidConfigException('Missing languages');
        } else if (is_callable($config['languages'])) {
            $config['languages'] = call_user_func($config['languages']);
        }
        if (empty($config['currencies'])) {
            throw new \yii\base\InvalidConfigException('Missing currencies');
        } else if (is_callable($config['currencies'])) {
            $config['currencies'] = call_user_func($config['currencies']);
        }
        if (empty($config['languagesCurrenciesAssignment'])) {
            throw new \yii\base\InvalidConfigException('Missing languagesCurrenciesAssignment');
        } else if (is_callable($config['languagesCurrenciesAssignment'])) {
            $config['languagesCurrenciesAssignment'] = call_user_func($config['languagesCurrenciesAssignment']);
        }
        if (empty($config['defaultCurrency'])) {
            throw new \yii\base\InvalidConfigException('Missing default currency');
        } else if (is_callable($config['defaultCurrency'])) {
            $config['defaultCurrency'] = call_user_func($config['defaultCurrency']);
        }
        parent::__construct($config);
    }

    public function init()
    {
        if (Yii::$app->request->cookies->getValue($this->currencyCookieName) == "usd" || Yii::$app->request->cookies->getValue($this->currencyCookieName) == "euro" || Yii::$app->request->cookies->getValue($this->currencyCookieName) == "cad" || Yii::$app->request->cookies->getValue($this->currencyCookieName) == "pound") {
            Yii::$app->session->set('user.currency', "USD");
            $this->saveCurrencyIntoCookie('USD');
        }
        $this->initLanguage();
        $this->initCurrency();
        parent::init();
    }

    /**
     * Setting the language of the site.
     */
    public function initLanguage()
    {
        if (isset($_GET['language-picker-hidden'])) {
            if ($this->isValidLanguage($_GET['language-picker-hidden'])) {
                return $this->saveLanguage($_GET['language-picker-hidden']);
            } else if (!Yii::$app->request->isAjax) {
                return $this->redirect();
            }
        } else if (Yii::$app->request->cookies->has($this->languageCookieName)) {
            if ($this->isValidLanguage(Yii::$app->request->cookies->getValue($this->languageCookieName))) {
                Yii::$app->language = Yii::$app->request->cookies->getValue($this->languageCookieName);
                return;
            } else {
                Yii::$app->response->cookies->remove($this->languageCookieName);
            }
        }
        $this->detectLanguage();
    }

    public function initCurrency()
    {
        //Fix for the currencies key values (reset the cookie and session value).
        if (isset($_GET['currency-picker-hidden'])) {
            if ($this->isValidCurrency($_GET['currency-picker-hidden'])) {
                return $this->saveCurrency($_GET['currency-picker-hidden']);
            } else if (!Yii::$app->request->isAjax) {
                return $this->redirect();
            }
        } else if (Yii::$app->request->cookies->has($this->currencyCookieName)) {
            if ($this->isValidCurrency(Yii::$app->request->cookies->getValue($this->currencyCookieName))) {
                Yii::$app->session->set('user.currency', Yii::$app->request->cookies->getValue($this->currencyCookieName));
                return;
            } else {
                Yii::$app->response->cookies->remove($this->currencyCookieName);
            }
        }
        $this->detectCurrency();
    }

    /**
     * Saving language into cookie and database.
     * @param string $language - The language to save.
     * @return static
     */
    public function saveLanguage($language)
    {
        Yii::$app->language = $language;
        $this->saveLanguageIntoCookie($language);
        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }
        if (Yii::$app->request->isAjax) {
            Yii::$app->end();
        }
        return $this->redirect();
    }

    /**
     * Determine language based on UserAgent.
     */
    public function detectLanguage()
    {
        $acceptableLanguages = Yii::$app->getRequest()->getAcceptableLanguages();
        foreach ($acceptableLanguages as $language) {
            if ($this->isValidLanguage($language)) {
                Yii::$app->language = $language;
                $this->saveLanguageIntoCookie($language);
                return;
            }
        }
        foreach ($acceptableLanguages as $language) {
            $pattern = preg_quote(substr($language, 0, 2), '/');
            foreach ($this->languages as $key => $value) {
                if (preg_match('/^' . $pattern . '/', $value) || preg_match('/^' . $pattern . '/', $key)) {
                    Yii::$app->language = $this->isValidLanguage($key) ? $key : $value;
                    $this->saveLanguageIntoCookie(Yii::$app->language);
                    return;
                }
            }
        }
    }

    public function saveLanguageIntoCookie($language)
    {
        $cookie = new \yii\web\Cookie([
            'name' => $this->languageCookieName,
            'domain' => $this->cookieDomain,
            'value' => $language,
            'expire' => time() + 86400 * $this->expireDays
        ]);
        Yii::$app->response->cookies->add($cookie);
    }

    /**
     * Saving currency into cookie and database.
     * @param string $currency - The currency to save.
     * @return static
     */
    public function saveCurrency($currency)
    {
        Yii::$app->session->set('user.currency', $currency);
        $this->saveCurrencyIntoCookie($currency);
        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }
        if (Yii::$app->request->isAjax) {
            Yii::$app->end();
        }
        return $this->redirect();
    }

    /**
     * Determine currency based on UserAgent.
     */
    public function detectCurrency()
    {
        $acceptableLanguages = Yii::$app->getRequest()->getAcceptableLanguages();
        foreach ($acceptableLanguages as $language) {
            if ($this->isValidLanguage($language)) {
                $currency = $this->getCurrencyByLanguage($language);
                Yii::$app->session->set('user.currency', $currency);
                $this->saveCurrencyIntoCookie($currency);
                return;
            }
        }
        foreach ($acceptableLanguages as $language) {
            $pattern = preg_quote(substr($language, 0, 2), '/');
            foreach ($this->languages as $key => $value) {
                if (preg_match('/^' . $pattern . '/', $value) || preg_match('/^' . $pattern . '/', $key)) {
                    $currency = $this->getCurrencyByLanguage($this->isValidLanguage($key) ? $key : $value);
                    Yii::$app->session->set('user.currency', $currency);
                    $this->saveCurrencyIntoCookie($currency);
                    return;
                }
            }
        }
    }

    public function getCurrencyByLanguage($language)
    {
        $currency = $this->languagesCurrenciesAssignment[$language]['key'];
        return isset($currency) ? $currency : $this->defaultCurrency;
    }

    public function saveCurrencyIntoCookie($currency)
    {
        $cookie = new \yii\web\Cookie([
            'name' => $this->currencyCookieName,
            'domain' => $this->cookieDomain,
            'value' => $currency,
            'expire' => time() + 86400 * $this->expireDays
        ]);
        Yii::$app->response->cookies->add($cookie);
    }

    /**
     * Redirects the browser to the referer URL.
     * @return static
     */
    private function redirect()
    {
        $redirect = Yii::$app->request->absoluteUrl == Yii::$app->request->referrer ? '/' : Yii::$app->request->referrer;
        return Yii::$app->response->redirect($redirect);
    }

    /**
     * Determines whether the language received as a parameter can be processed.
     */
    private function isValidLanguage($language)
    {
        return is_string($language) && (isset($this->languages[$language]) || in_array($language, $this->languages));
    }

    /**
     * Determines whether the currency received as a parameter can be processed.
     */
    private function isValidCurrency($currency)
    {
        return is_string($currency) && (isset($this->currencies[$currency]) || in_array($currency, $this->currencies));
    }

}