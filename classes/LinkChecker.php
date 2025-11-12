<?php
namespace Grav\Plugin\Classes;

use Grav\Common\Grav;

class LinkChecker
{
    protected $grav;
    protected $config;

    public function __construct($grav, $config)
    {
        $this->grav = $grav;
        $this->config = $config;
    }

    public function extractLinks(string $html): array
    {
        preg_match_all('/<a href="(.*)">/i', $html, $matches);
        return array_unique($matches[1] ?? []);
    }

    public function checkInternal(string $url, string $baseUrl): bool
    {
        $pages = $this->grav['pages'];
        return (bool) $pages->find($url) || file_exists($baseUrl . $url);
    }

    public function checkExternal(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config['user_agent']);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode >= 200 && $httpcode < 400;
    }
}