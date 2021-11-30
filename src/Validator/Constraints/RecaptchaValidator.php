<?php

declare(strict_types=1);

namespace Leapt\CoreBundle\Validator\Constraints;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ValidatorException;

class RecaptchaValidator extends ConstraintValidator
{
    /**
     * The reCAPTCHA server URL's.
     */
    public const RECAPTCHA_VERIFY_SERVER = 'https://www.google.com';

    /**
     * Recaptcha Private Key.
     */
    protected bool $privateKey;

    public function __construct(
        protected bool $enabled,
        string $privateKey,
        protected RequestStack $requestStack,
        protected array $httpProxy,
        protected bool $verifyHost,
    ) {
        $this->privateKey = $privateKey;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        // if recaptcha is disabled, always valid
        if (!$this->enabled) {
            return;
        }

        // define variable for recaptcha check answer
        if (method_exists(RequestStack::class, 'getMainRequest')) {
            $mainRequest = $this->requestStack->getMainRequest();
        } else {
            $mainRequest = $this->requestStack->getMasterRequest();
        }
        $remoteip = $mainRequest->getClientIp();
        $answer = $mainRequest->get('g-recaptcha-response');

        // Verify user response with Google
        $response = $this->checkAnswer($this->privateKey, $remoteip, $answer);

        if (false === $response || true !== $response['success']) {
            $this->context->addViolation($constraint->message);
        }
        // Perform server side hostname check
        elseif ($this->verifyHost && $mainRequest->getHost() !== $response['hostname']) {
            $this->context->addViolation($constraint->invalidHostMessage);
        }
    }

    /**
     * Calls an HTTP POST function to verify if the user's guess was correct.
     *
     * @return bool
     *
     *@throws ValidatorException When missing remote ip
     */
    private function checkAnswer(string $privateKey, string $remoteip, string $answer): mixed
    {
        if (null === $remoteip || '' === $remoteip) {
            throw new ValidatorException('For security reasons, you must pass the remote ip to reCAPTCHA');
        }

        // discard spam submissions
        if (null === $answer || '' === $answer) {
            return false;
        }

        $response = $this->httpGet(self::RECAPTCHA_VERIFY_SERVER, '/recaptcha/api/siteverify', [
            'secret'   => $privateKey,
            'remoteip' => $remoteip,
            'response' => $answer,
        ]);

        return json_decode($response, true);
    }

    /**
     * Submits an HTTP POST to a reCAPTCHA server.
     *
     * @return array response
     */
    private function httpGet(string $host, string $path, array $data): false|string
    {
        $host = sprintf('%s%s?%s', $host, $path, http_build_query($data));

        $context = $this->getResourceContext();

        return file_get_contents($host, false, $context);
    }

    /**
     * @return resource|null
     */
    private function getResourceContext()
    {
        if (null === $this->httpProxy['host'] || null === $this->httpProxy['port']) {
            return null;
        }

        $options = [];
        foreach (['http', 'https'] as $protocol) {
            $options[$protocol] = [
                'method'          => 'GET',
                'proxy'           => sprintf('tcp://%s:%s', $this->httpProxy['host'], $this->httpProxy['port']),
                'request_fulluri' => true,
            ];

            if (null !== $this->httpProxy['auth']) {
                $options[$protocol]['header'] = sprintf('Proxy-Authorization: Basic %s', base64_encode($this->httpProxy['auth']));
            }
        }

        return stream_context_create($options);
    }
}
