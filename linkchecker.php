<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\YamlFile;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

include_once "classes/LinkChecker.php";

/**
 * Class LinkcheckerPlugin
 * @package Grav\Plugin
 */
class LinkcheckerPlugin extends Plugin
{
    private $debug = false;
    public static $Parsedown;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                // Uncomment following line when plugin requires Grav < 1.7
                // ['autoload', 100000],
                ['onPluginsInitialized', 0],
            ],
            'onSchedulerInitialized' => ['onSchedulerInitialized', 0],
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->enable([
                // Put your main events here
               'onAdminDashboard' => ['onAdminDashboard', 0],
               'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
               'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            ]);
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            // Put your main events here
        ]);
        static::$Parsedown = new \Parsedown;
        $this->debug = $this->config()['debug'];
        if ($this->debug) $this->grav['log']->debug($this->grav['uri']->url());
        $this->catchLinkchecker($this->grav['uri']->url());
    }

    private function catchLinkchecker($url): void
    {
        if ($url == '/linkchecker') {
            $this->runScheduledCheck();
            exit;
        }
    }

	public function onAdminDashboard(Event $event): void
    {
        $this->grav['twig']->plugins_hooked_dashboard_widgets_bottom[] = [
            'name' => 'Broken Links',
            'template' => 'linkchecker-widget',
        ];
    }

    public function onSchedulerInitialized(Event $event): void
    {
        $frequency = $this->config->get('plugins.linkchecker.cron_time');
        $email = explode('/', $this->config->get('plugins.linkchecker.email_report'));
        $scheduler = $event['scheduler'];
        $job = $scheduler->addFunction('Grav\Plugin\LinkcheckerPlugin::runScheduledCheck', [], 'linkchecker');
        $job->at($frequency);
        $job->output(GRAV_ROOT . '/logs/linkchecker.out');
        $job->backlink('/plugins/linkchecker');
        if ($email !== '') $job->email($email);
    }
	
    public static function getLinkStatus($type, $url, $succeeded, $path) : array
    {
        if (str_starts_with($path, GRAV_ROOT . '/user/pages')) $path = substr($path, strlen(GRAV_ROOT . '/user/pages'));
        return [
            'type' => $type,
            'url' => $url,
            'status' => $succeeded ? 'OK' : 'BROKEN',
            'page' => $path,
        ];
    }

    public static function getBasePath($url, $path): string
    {
        $basePath = $path;
        if (str_starts_with($url, '/compositions') || str_starts_with($url, '/publications') || str_starts_with($url, '/software')) $basePath = GRAV_ROOT . '/user/pages';
        if (str_starts_with($url, '/midifile-osax')) $basePath = GRAV_ROOT . '/user/pages/software';
        return $basePath;
    }

    public static function doApplyMarkdown($content): string
    {
        if (!empty($content)) {
            $content = static::$Parsedown->text($content);
            // take out surrounding <p> </p>
            if ((substr($content, 0, 3) == '<p>') && (substr($content, -4, 4) == '</p>')) $content = substr($content, 3, -4);
        }
        return $content;
    }
	
	private static function extractLinksFromMarkdown($header, $checker, $debug): array
	{
		$grav = Grav::instance();
		$links = array();
		foreach ($header as $text) {
			if (is_array($text)) {
				$header_links = static::extractLinksFromMarkdown($text, $checker, $debug);
				if (isset($header_links)) {
					if (is_array($header_links)) $links = $links + $header_links;
					else $links = $links + array($header_links);
				}
			}
			else {
		        if ($debug) $grav['log']->debug('Extracting links from header ' . $text);
				$content = static::$Parsedown->text($text);
				$text_without_quote = str_replace('"', "", $content);
		        if ($debug) $grav['log']->debug('Parsing links from header ' . $text_without_quote);
				$header_links = $checker->extractLinks($text_without_quote);
				if (isset($header_links)) {
					if (is_array($header_links)) {
						$links = $links + $header_links;
						foreach ($header_links as $item) {
							if ($debug) $grav['log']->debug('Found link ' . $item);
						}
					}
					else {
						$links = $links + array($header_links);	
						if ($debug) $grav['log']->debug('Found link ' . $header_links);
						}
				}
			}
			//if ($debug) $grav['log']->debug('Extracted links from header ' . $text . ' value '. $links);
		}
		return $links;
	}
	
    public static function runScheduledCheck(): string
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('plugins.linkchecker');
        $debug = $config['debug'];
        $checkinternal = $config['check_internal'];
        $checkexternal = $config['check_external'];
        $onlybroken = $config['only_broken'];
        $checker = new \Grav\Plugin\Classes\LinkChecker($grav, $config);

        static::$Parsedown = new \Parsedown;

        $results = [];

        //scan urls in pages
        $pages = new \Grav\Common\Page\Pages($grav);
        $pages->init();
        $grav['twig']->init();
        foreach ($pages->instances() as $path => $page) {
            //if ($debug) $grav['log']->debug('Checking links on page '. $page->title());
            $html = $page->content();
            $links = $checker->extractLinks($html);
			$headers = (array)$page->header();
			foreach ($headers as $header) {
				if (!is_array($header))$temp = array($header);
				else $temp = $header;
				$links = $links + static::extractLinksFromMarkdown($temp, $checker, $debug);
			}
            if ($links == []) continue; 
            //if ($debug) $grav['log']->debug('Found links ' . implode(', ', $links) . ' on page '. $page->title());
            foreach ($links as $url) {
                $isexternal = str_starts_with($url, 'http') ? true : false;
                if ($isexternal) {
                    if ($checkexternal) $succeeded = $checker->checkExternal($url);
                }
                else {
                    if ($checkinternal) $succeeded = $checker->checkInternal($url, static::getBasePath($url, $path));
                }

                if (!$onlybroken) $results[] = static::getLinkStatus('page', $url, $succeeded, $path);
                else if (!$succeeded) $results[] = static::getLinkStatus('page', $url, $succeeded, $path);
            }
        }

        //scan urls in text macros
        $config = $grav['config']->get('plugins.pswadditions');
        foreach ($config['psw_macros'] as $macro => $value) {
            //if ($debug) $grav['log']->debug('Checking links in macro ' . $macro . ' value '. $value);
            $html = static::doApplyMarkdown($value);
            $links = $checker->extractLinks($html);
            if ($links == []) continue; 
            //if ($debug) $grav['log']->debug('Found links ' . implode(', ', $links) . ' in macro ' . $macro . ' value ' . $html);
            foreach ($links as $url) {
                $isexternal = str_starts_with($url, 'http') ? true : false;
                if ($isexternal) {
                    if ($checkexternal) $succeeded = $checker->checkExternal($url);
                }
                else {
                    if (str_starts_with($url, 'mailto')) $succeeded = true;
                    else if ($checkinternal) $succeeded = $checker->checkInternal($url, static::getBasePath($url, GRAV_ROOT . '/user/pages'));
                }

                if (!$onlybroken) $results[] = static::getLinkStatus('macro', $url, $succeeded, $macro);
                else if (!$succeeded) $results[] = static::getLinkStatus('macro', $url, $succeeded, $macro);
            }
        }

        $filename = DATA_DIR . 'linkchecker/results.yaml';
        file_put_contents($filename, Yaml::dump($results, 2));
		//grav['log']->info('Linkchecker run completed');
		$message = 'Linkchecker found ' . count($results) . ' broken links.';
		$grav['scheduler']->getLogger()->info($message);
		$link_to_host = $grav['config']->get('plugins.linkchecker.include_current_host');
		if ($link_to_host !== '') $message .= ' Go to https://' . $link_to_host . '/admin/dashboard for more details.';
		return $message;
    }
	
	public function onTwigSiteVariables(): void
	{
        $filename = DATA_DIR . 'linkchecker/results.yaml';
        $broken = file_exists($filename) ? Yaml::parseFile($filename) : [];
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $twig->twig_vars['broken_links'] = $broken;
	}

	public function onAdminTwigTemplatePaths(Event $event): void
	{
	    $paths = $event['paths'];
	    $paths[] = __DIR__ . '/admin/themes/grav/templates';
	    $event['paths'] = $paths;
	}
}
