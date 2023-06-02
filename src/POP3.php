<?php
namespace Yng\Mailer;
use Yng\Mailer\Exception\EmailConfigException;

/**
 * PHPMailer POP-Before-SMTP 身份验证类。
 * 专门供 PHPMailer 用于 RFC1939 POP-before-SMTP 身份验证。
 * 1) 该类不支持APOP认证。
 * 2) 打开和关闭大量 POP3 连接可能会很慢。 如果你需要
 * 发送一批电子邮件然后只需在开始时执行一次身份验证，
 * 然后循环遍历您的邮件发送脚本。 提供这个过程不
 * 比您的 POP3 服务器上的验证期持续时间更长，您应该没问题。
 * 3) 这真是古老的技术； 你应该只需要用它来与非常旧的系统对话。
 * 4) 这个 POP3 类是故意轻量级和不完整的，只实现
 * 足以进行身份验证。
 * 如果您想要更完整的课程，可以使用其他适用于 PHP 的 POP3 课程。
 */
class POP3
{
    /**
     * POP3 YNGMailer版本号
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * 默认端口
     *
     * @var int
     */
    const DEFAULT_PORT = 110;

    /**
     * 默认超时时间(单位秒)
     *
     * @var int
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * POP3 调试模式
     * @see POP3::DEBUG_OFF: 无输出
     * @see POP3::DEBUG_SERVER: 输出服务器消息、连接/服务器错误
     * @see POP3::DEBUG_CLIENT: 输出客户端和服务器消息，连接/服务器错误
     *
     * @var int
     */
    public $do_debug = self::DEBUG_OFF;

    /**
     * 主机地址
     *
     * @var string
     */
    public $host;

    /**
     * 端口号
     *
     * @var int
     */
    public $port;

    /**
     * 超时值(单位秒)
     *
     * @var int
     */
    public $tval;

    /**
     * POP3用户名
     *
     * @var string
     */
    public $username;

    /**
     * POP3密码
     *
     * @var string
     */
    public $password;

    /**
     * POP3 连接套接字的资源句柄
     *
     * @var resource
     */
    protected $pop_conn;

    /**
     * 是否连接状态
     *
     * @var bool
     */
    protected $connected = false;

    /**
     * 错误信息
     *
     * @var array
     */
    protected $errors = [];

    /**
     * 换行常量
     */
    const LE = "\r\n";

    /**
     * 调试模式1：无输出
     *
     * @var int
     */
    const DEBUG_OFF = 0;

    /**
     * 显示服务器 -> 客户端消息的调试级别
     * 还显示客户端连接错误或来自服务器的错误
     *
     * @var int
     */
    const DEBUG_SERVER = 1;

    /**
     * 调试级别显示客户端 -> 服务器和服务器 -> 客户端消息
     *
     * @var int
     */
    const DEBUG_CLIENT = 2;

    /**
     * 用于 SMTP 之前的多合一 POP 的简单静态包装器
     *
     * @param string   $host        The hostname to connect to
     * @param int|bool $port        The port number to connect to
     * @param int|bool $timeout     The timeout value
     * @param string   $username
     * @param string   $password
     * @param int      $debug_level
     *
     * @return bool
     */
    public static function popBeforeSmtp(
        $host,
        $port = false,
        $timeout = false,
        $username = '',
        $password = '',
        $debug_level = 0
    ) {
        $pop = new self();

        return $pop->authorise($host, $port, $timeout, $username, $password, $debug_level);
    }

    /**
     * 使用 POP3 服务器进行身份验证。
     * 连接、登录、断开序列
     * 适用于 POP-before SMTP 授权。
     *
     * @param string   $host        The hostname to connect to
     * @param int|bool $port        The port number to connect to
     * @param int|bool $timeout     The timeout value
     * @param string   $username
     * @param string   $password
     * @param int      $debug_level
     *
     * @return bool
     */
    public function authorise($host, $port = false, $timeout = false, $username = '', $password = '', $debug_level = 0)
    {
        $this->host = $host;
        //如果未提供端口值，则使用默认值
        if (false === $port) {
            $this->port = static::DEFAULT_PORT;
        } else {
            $this->port = (int) $port;
        }
        //如果没有提供超时值，则使用默认值
        if (false === $timeout) {
            $this->tval = static::DEFAULT_TIMEOUT;
        } else {
            $this->tval = (int) $timeout;
        }
        $this->do_debug = $debug_level;
        $this->username = $username;
        $this->password = $password;
        //重置错误日志
        $this->errors = [];
        //连接
        $result = $this->connect($this->host, $this->port, $this->tval);
        if ($result) {
            $login_result = $this->login($this->username, $this->password);
            if ($login_result) {
                $this->disconnect();

                return true;
            }
        }
        //不管是否登录成功我们都需要断开连接
        $this->disconnect();

        return false;
    }

    /**
     * 连接POP3服务
     *
     * @param string   $host
     * @param int|bool $port
     * @param int      $tval
     *
     * @return bool
     */
    public function connect($host, $port = false, $tval = 30)
    {
        //是否为连接状态
        if ($this->connected) {
            return true;
        }

        //在 Windows 上，如果主机名不存在，这将引发 PHP 警告错误。
        //与其用@fsockopen抑制它，不如干净地捕获它
        set_error_handler([$this, 'catchWarning']);

        if (false === $port) {
            $port = static::DEFAULT_PORT;
        }

        //开始连接pop3服务
        $errno = 0;
        $errstr = '';
        $this->pop_conn = fsockopen(
            $host, //POP3 Host
            $port, //Port #
            $errno, //Error Number
            $errstr, //Error Message
            $tval
        ); //Timeout (seconds)

        //恢复错误处理程序
        restore_error_handler();

        //看看是否处于连接中
        if (false === $this->pop_conn) {
            //It would appear not...
            $this->setError(
                "Failed to connect to server {$host} on port {$port}. errno: {$errno}; errstr: {$errstr}"
            );

            return false;
        }

        //添加超时时长
        stream_set_timeout($this->pop_conn, $tval, 0);

        //获取pop3服务响应
        $pop3_response = $this->getResponse();

        //检测是否正常
        if ($this->checkResponse($pop3_response)) {
            //连接已建立，POP3 服务器正在通话
            $this->connected = true;

            return true;
        }

        return false;
    }

    /**
     * 登录pop3服务
     * 不支持 APOP（RFC 2828、4949）
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function login($username = '', $password = '')
    {
        if (!$this->connected) {
            $this->setError('Not connected to POP3 server');
            return false;
        }
        if (empty($username)) {
            $username = $this->username;
        }
        if (empty($password)) {
            $password = $this->password;
        }

        //发送用户名
        $this->sendString("USER $username" . static::LE);
        $pop3_response = $this->getResponse();
        if ($this->checkResponse($pop3_response)) {
            //发送密码
            $this->sendString("PASS $password" . static::LE);
            $pop3_response = $this->getResponse();
            if ($this->checkResponse($pop3_response)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 断开与 POP3 服务器的连接
     */
    public function disconnect()
    {
        // 如果根本无法连接，则无需断开连接
        if ($this->pop_conn === false) {
            return;
        }

        $this->sendString('QUIT' . static::LE);

        // RFC 1939 显示 POP3 服务器向 QUIT 命令发送 +OK 响应。
        // 尝试获取它。 忽略此处的任何失败
        try {
            $this->getResponse();
        } catch (EmailConfigException $e) {
            //Do nothing
        }

        //QUIT 命令可能会导致守护进程退出，这会杀死我们的连接
        //所以忽略这里的错误
        try {
            @fclose($this->pop_conn);
        } catch (EmailConfigException $e) {
            //Do nothing
        }

        // 重置属性值
        $this->connected = false;
        $this->pop_conn  = false;
    }

    /**
     * 从 POP3 服务器获得响应
     *
     * @param int $size The maximum number of bytes to retrieve
     *
     * @return string
     */
    protected function getResponse($size = 128)
    {
        $response = fgets($this->pop_conn, $size);
        if ($this->do_debug >= self::DEBUG_SERVER) {
            echo 'Server -> Client: ', $response;
        }

        return $response;
    }

    /**
     * 将原始数据发送到 POP3 服务器
     *
     * @param string $string
     *
     * @return int
     */
    protected function sendString($string)
    {
        if ($this->pop_conn) {
            if ($this->do_debug >= self::DEBUG_CLIENT) { //Show client messages when debug >= 2
                echo 'Client -> Server: ', $string;
            }

            return fwrite($this->pop_conn, $string, strlen($string));
        }

        return 0;
    }

    /**
     * 检查 POP3 服务器响应。
     * 寻找 +OK 或 -ERR
     *
     * @param string $string
     *
     * @return bool
     */
    protected function checkResponse($string)
    {
        if (strpos($string, '+OK') !== 0) {
            $this->setError("Server reported an error: $string");

            return false;
        }

        return true;
    }

    /**
     * 向内部错误存储添加错误。
     * 如果启用，还会显示调试输出
     *
     * @param string $error
     */
    protected function setError($error)
    {
        $this->errors[] = $error;
        if ($this->do_debug >= self::DEBUG_SERVER) {
            echo '<pre>';
            foreach ($this->errors as $e) {
                print_r($e);
            }
            echo '</pre>';
        }
    }

    /**
     * 获取错误消息数组
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * POP3 连接错误处理程序
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     */
    protected function catchWarning($errno, $errstr, $errfile, $errline)
    {
        $this->setError(
            'Connecting to the POP3 server raised a PHP warning:' .
            "errno: $errno errstr: $errstr; errfile: $errfile; errline: $errline"
        );
    }
}
