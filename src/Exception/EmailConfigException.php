<?php
declare(strict_types=1);

namespace Yng\Mailer\Exception;

/**
 * 邮件配置错误异常
 */
class EmailConfigException extends \Exception
{
    /**
     * 美化错误消息输出
     *
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}