<?php

namespace Leapt\CoreBundle\Form\Type;

use Leapt\CoreBundle\Locale\LocaleResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field for entering a recaptcha text.
 */
class RecaptchaType extends AbstractType
{
    /**
     * The reCAPTCHA server URL's.
     */
    const RECAPTCHA_API_SERVER = 'https://www.google.com/recaptcha/api.js';
    const RECAPTCHA_API_JS_SERVER = '//www.google.com/recaptcha/api/js/recaptcha_ajax.js';

    /**
     * The public key.
     *
     * @var string
     */
    protected $publicKey;

    /**
     * Enable recaptcha?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Use AJAX api?
     *
     * @var bool
     */
    protected $ajax;

    /**
     * @var LocaleResolver
     */
    protected $localeResolver;

    protected $scripts = [];

    /**
     * @param string $publicKey Recaptcha public key
     * @param bool   $enabled   Recaptache status
     * @param bool   $ajax      Ajax status
     */
    public function __construct($publicKey, $enabled, $ajax, LocaleResolver $localeResolver)
    {
        $this->publicKey = $publicKey;
        $this->enabled = $enabled;
        $this->ajax = $ajax;
        $this->localeResolver = $localeResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'leapt_core_recaptcha_enabled' => $this->enabled,
            'leapt_core_recaptcha_ajax'    => $this->ajax,
        ]);

        if (!$this->enabled) {
            return;
        }

        if (!isset($options['language'])) {
            $options['language'] = $this->localeResolver->resolve();
        }

        if (!$this->ajax) {
            $view->vars = array_replace($view->vars, [
                'url_challenge' => sprintf('%s?hl=%s', self::RECAPTCHA_API_SERVER, $options['language']),
                'public_key'    => $this->publicKey,
            ]);
        } else {
            $view->vars = array_replace($view->vars, [
                'url_api'    => self::RECAPTCHA_API_JS_SERVER,
                'public_key' => $this->publicKey,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound'      => false,
            'language'      => $this->localeResolver->resolve(),
            'public_key'    => null,
            'url_challenge' => null,
            'url_noscript'  => null,
            'attr'          => [
                'options' => [
                    'theme'           => 'light',
                    'type'            => 'image',
                    'size'            => 'normal',
                    'callback'        => null,
                    'expiredCallback' => null,
                    'bind'            => null,
                    'defer'           => false,
                    'async'           => false,
                    'badge'           => null,
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return TextType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'leapt_core_recaptcha';
    }

    /**
     * Gets the Javascript source URLs.
     *
     * @param string $key The script name
     *
     * @return string The javascript source URL
     */
    public function getScriptURL($key)
    {
        return isset($this->scripts[$key]) ? $this->scripts[$key] : null;
    }

    /**
     * Gets the public key.
     *
     * @return string The javascript source URL
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}