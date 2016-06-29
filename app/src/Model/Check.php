<?php
namespace HomoChecker\Model;

use mpyw\Co\Co;
use mpyw\Co\CURLException;
use HomoChecker\Model\Validator\HeaderValidator;
use HomoChecker\Model\Validator\DOMValidator;
use HomoChecker\Model\Validator\URLValidator;

class Check {
    const TIMEOUT = 5000;

    public function __construct() {
        $this->validators = [
            new HeaderValidator,
            new DOMValidator,
            new URLValidator,
        ];
    }

    public function initialize(string $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLINFO_HEADER_OUT       => true,
            CURLOPT_AUTOREFERER       => true,
            CURLOPT_CONNECTTIMEOUT_MS => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION    => true,
            CURLOPT_MAXCONNECTS       => 16,
            CURLOPT_MAXREDIRS         => 5,
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_SSL_VERIFYPEER    => false,
            CURLOPT_TIMEOUT_MS        => self::TIMEOUT,
            CURLOPT_USERAGENT         => 'Homozilla/5.0 (Checker/1.14.514; homOSeX 8.10)',
        ]);
        return $ch;
    }

    protected function timer() {
        return microtime(true) - $this->time;
    }

    protected function validate(Homo $homo) {
        $ch = $this->initialize($homo->url);
        $body = yield $ch;

        if ($body instanceof CURLException || !curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
            return 'ERROR';
        }

        foreach ($this->validators as $validator) {
            if (($status = $validator($ch, $body))) {
                return $status;
            }
        }

        return 'WRONG';
    }

    public function execute(callable $callback = null) {
        $this->time = microtime(true);
        $requests = [];
        foreach (Homo::getAll() as $homo) {
            $requests[] = function () use ($homo, $callback) {
                $status = yield $this->validate($homo);
                $duration = $this->timer();
                $icon = yield Icon::get($homo->screen_name);

                $response = new HomoResponse($homo, $icon, $status, $duration);
                if ($callback) {
                    $callback($response);
                }
                return $response;
            };
        }
        Co::wait($requests, ['throw' => false]);
    }
}