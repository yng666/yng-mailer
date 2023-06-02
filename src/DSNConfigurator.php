<?php

namespace Yng\Mailer;
use Yng\Mailer\Exception\EmailConfigException;

/**
 * YNGMailer - PHP邮件创建和传输类
 * 使用版本要求：PHP5.5+
 * 用DSN字符串配置YNGMailer
 */
class DSNConfigurator
{
    /**
     * 用DSN字符串配置PHPMailer
     *
     * @param string $dsn        DSN
     * @param bool   $exceptions
     *
     * @return YNGMailer
     */
    public static function mailer($dsn, $exceptions = null)
    {
        static $configurator = null;

        if (null === $configurator) {
            $configurator = new DSNConfigurator();
        }

        return $configurator->configure(new YNGMailer($exceptions), $dsn);
    }

    /**
     * 使用 DSN 字符串配置 YNGMailer 实例
     *
     * @param YNGMailer $mailer YNGMailer实例
     * @param string    $dsn    DSN
     *
     * @return YNGMailer
     */
    public function configure(YNGMailer $mailer, $dsn)
    {
        $config = $this->parseDSN($dsn);

        $this->applyConfig($mailer, $config);

        return $mailer;
    }

    /**
     * 解析 DSN 字符串
     * @param string $dsn DSN
     * @throws EmailConfigException 异常
     *
     * @return array 配置
     */
    private function parseDSN($dsn)
    {
        $config = $this->parseUrl($dsn);

        if (false === $config || !isset($config['scheme']) || !isset($config['host'])) {
            throw new EmailConfigException(
                sprintf('Malformed DSN: "%s".', $dsn)
            );
        }

        if (isset($config['query'])) {
            parse_str($config['query'], $config['query']);
        }

        return $config;
    }

    /**
     * 将配置应用于邮件程序
     * @param YNGMailer $mailer YNGMailer实例
     * @param array     $config 配置
     * @throws EmailConfigException 异常
     */
    private function applyConfig(YNGMailer $mailer, $config)
    {
        switch ($config['scheme']) {
            case 'mail':
                $mailer->isMail();
            break;
            case 'sendmail':
                $mailer->isSendmail();
            break;
            case 'qmail':
                $mailer->isQmail();
            break;
            case 'smtp':
            case 'smtps':
                $mailer->isSMTP();
                $this->configureSMTP($mailer, $config);
            break;
            default:
                throw new EmailConfigException(
                    sprintf(
                        'Invalid scheme: "%s". Allowed values: "mail", "sendmail", "qmail", "smtp", "smtps".',
                        $config['scheme']
                    )
                );
            break;
        }

        if (isset($config['query'])) {
            $this->configureOptions($mailer, $config['query']);
        }
    }

    /**
     * 配置SMTP
     *
     * @param YNGMailer $mailer YNGMailer实例
     * @param array     $config 配置
     */
    private function configureSMTP($mailer, $config)
    {
        $isSMTPS = 'smtps' === $config['scheme'];

        if ($isSMTPS) {
            $mailer->SMTPSecure = YNGMailer::ENCRYPTION_STARTTLS;
        }

        $mailer->Host = $config['host'];

        if (isset($config['port'])) {
            $mailer->Port = $config['port'];
        } elseif ($isSMTPS) {
            $mailer->Port = SMTP::DEFAULT_SECURE_PORT;
        }

        $mailer->SMTPAuth = isset($config['user']) || isset($config['pass']);

        if (isset($config['user'])) {
            $mailer->Username = $config['user'];
        }

        if (isset($config['pass'])) {
            $mailer->Password = $config['pass'];
        }
    }

    /**
     * 配置选项
     *
     * @param YNGMailer $mailer  YNGMailer实例
     * @param array     $options 选项
     *
     * @throws EmailConfigException 异常
     */
    private function configureOptions(YNGMailer $mailer, $options)
    {
        $allowedOptions = get_object_vars($mailer);

        unset($allowedOptions['Mailer']);
        unset($allowedOptions['SMTPAuth']);
        unset($allowedOptions['Username']);
        unset($allowedOptions['Password']);
        unset($allowedOptions['Hostname']);
        unset($allowedOptions['Port']);
        unset($allowedOptions['ErrorInfo']);

        $allowedOptions = \array_keys($allowedOptions);

        foreach ($options as $key => $value) {
            if (!in_array($key, $allowedOptions)) {
                throw new EmailConfigException(
                    sprintf(
                        'Unknown option: "%s". Allowed values: "%s"',
                        $key,
                        implode('", "', $allowedOptions)
                    )
                );
            }

            switch ($key) {
                case 'AllowEmpty':
                case 'SMTPAutoTLS':
                case 'SMTPKeepAlive':
                case 'SingleTo':
                case 'UseSendmailOptions':
                case 'do_verp':
                case 'DKIM_copyHeaderFields':
                    $mailer->$key = (bool) $value;
                break;

                case 'Priority':
                case 'SMTPDebug':
                case 'WordWrap':
                    $mailer->$key = (int) $value;
                break;

                default:
                    $mailer->$key = $value;
                break;
            }
        }
    }

    /**
     * 解析url地址
     * 内置 parse_url 函数的包装器，用于解决 PHP 5.5 中的错误
     *
     * @param string $url URL
     *
     * @return array|false
     */
    protected function parseUrl($url)
    {
        if (\PHP_VERSION_ID >= 50600 || false === strpos($url, '?')) {
            return parse_url($url);
        }

        $chunks = explode('?', $url);
        if (is_array($chunks)) {
            $result = parse_url($chunks[0]);
            if (is_array($result)) {
                $result['query'] = $chunks[1];
            }
            return $result;
        }

        return false;
    }
}
