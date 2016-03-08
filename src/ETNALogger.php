<?php

namespace ETNA\Silex\Provider\Config;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Silex\Provider\MonologServiceProvider;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Logger;

class ETNALogger implements ServiceProviderInterface
{
    /**
     *
     * @{inherit doc}
     */
    public function register(Container $app)
    {
        if (true !== isset($app["application_path"])) {
            throw new \Exception('$app["application_path"] is not set');
        }

        if (true !== isset($app["application_name"])) {
            throw new \Exception('$app["application_name"] is not set');
        }

        $app_name = $app["application_name"];

        $app->register(
            new MonologServiceProvider(),
            [
                'monolog.logfile'               => "{$app["application_path"]}/tmp/log/{$app_name}.log",
                'monolog.name'                  => $app_name,
                'monolog.level'                 => (true === $app['debug']) ? Logger::DEBUG : Logger::ERROR,
                'monolog.fingerscrossed.level'  => Logger::CRITICAL,
                'monolog.rotatingfile'          => true,
                'monolog.rotatingfile.maxfiles' => 7
            ]
        );

        if (true !== $app['debug']) {
            $syslog    = new SyslogHandler($app_name, "user");
            $formatter = new LineFormatter("%message% %context%");
            $syslog->setFormatter($formatter);
            $app['monolog']->pushHandler($syslog);

            $this->slackLogger($app);
        }

        $app['logs'] = $app['monolog'];
    }

    private function slackLogger(Application $app)
    {
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
}
