<?php

namespace ETNA\Silex\Provider\Logger;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Silex\Provider\MonologServiceProvider;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Logger;

/**
 *  $app['logs']: log provider for Etna Apps.
 *  - default logging on stderr
 */
class ETNALogger implements ServiceProviderInterface
{
    /**
     *
     * @{inherit doc}
     */
    public function register(Container $app)
    {
        if (true !== isset($app["application_name"])) {
            throw new \Exception('$app["application_name"] is not set');
        }
        $app_name = $app["application_name"];
        $is_debug_enabled = isset($app["debug"]) && (true === $app['debug']);
        $log_level = $this->get_log_level($is_debug_enabled);

        // The fingerCrossed level allows live redefinition of the log level: When a log
        // with level above threshold is emitted, then the log level will be lowered.
        $monolog_options = [
            'monolog.name'                  => $app_name,
            'monolog.level'                 => $log_level,
            'monolog.fingerscrossed.level'  => Logger::CRITICAL,
        ];

        
        $log_file = getenv("ETNA_LOGFILE");
        if (false === $log_file) {
            // default logging to stderr
            $monolog_options['monolog.logfile'] = "php://stderr";
        } else {
            $monolog_options['monolog.logfile'] = $log_file;
            $monolog_options['monolog.rotatingfile'] = true;
            $monolog_options['monolog.rotatingfile.maxfiles'] = 7;
        }
        
        $app->register(new MonologServiceProvider(), $monolog_options);

        if ($this->should_configure_syslog($is_debug_enabled)) {
            $syslog    = new SyslogHandler($app_name, "user");
            $formatter = new LineFormatter("%message% %context%");
            $syslog->setFormatter($formatter);
            $app['monolog']->pushHandler($syslog);
        }

        /* 2021 - I have disabled Slack integration for the following reason:
         *  - we use Rocket instead of Slack now
         *  - integration with Rocket have not been tested.
         *  - most individual api are beeing phased out.
         */
        // $this->setupSlackLogger($app, $is_debug_enabled);
        
        $app['logs'] = $app['monolog'];
    }

    private function setupSlackLogger(Application $app, $is_debug_enabled)
    {
        if ($is_debug_enabled) {
            return false;
        }
        
        $slack_token = getenv("SLACK_TOKEN");
        if (false === $slack_token) {
            return;
        }

        $slack           = new SlackHandler($slack_token, "#error", $app["application_name"]);
        $slack_formatter = new LineFormatter(
            "```[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n```",
            "Y-m-d H:i:s",
            true,
            true
        );
        $slack->setLevel(\Monolog\Logger::ERROR);
        $slack->setFormatter($slack_formatter);
        $app['monolog']->pushHandler($slack);

    }

    private function get_log_level(bool $debug_enabled)
    {
        $default_level = ($debug_enabled ? Logger::DEBUG : Logger::INFO);
        $env_loglevel = getenv('ETNA_LOGLEVEL');
        if (false === $env_loglevel) {
            // ETNA_LOGLEVEL is not defined in environment
            return $default_level;
        }
        switch (strtolower($env_loglevel)) {
        case 'debug': return Logger::DEBUG;
        case 'info':  return Logger::INFO;
        case 'warning': return Logger::WARNING;
        case 'error': return Logger::ERROR;
        case 'critical': return Logger::CRITICAL;
        default: return $default_level;
        }
    }

    private function should_configure_syslog(bool $debug_enabled)
    {
        if ($debug_enabled) {
            return false;
        }

        $env_syslog = getenv('ETNA_SYSLOG_ENABLED');
        if (false === $env_syslog) {
            return false;
        }
        switch (strtolower($env_syslog)) {
        case '1':
        case 'yes':
        case 'true':
            return true;
        default:
            return false;
        }
    }
}
