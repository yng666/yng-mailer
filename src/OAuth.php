<?php
namespace Yng\Mailer;

use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;

/**
 * OAuth2 身份验证包装器类
 * Uses the oauth2-client package from the League of Extraordinary Packages.
 *
 * @see http://oauth2-client.thephpleague.com
 */
class OAuth implements OAuthTokenProvider
{
    /**
     * League OAuth Client Provider 的一个实例
     *
     * @var AbstractProvider
     */
    protected $provider;

    /**
     * 当前OAuth认证的access_token.
     *
     * @var AccessToken
     */
    protected $oauthToken;

    /**
     * 用户的电子邮件地址，通常用作登录 ID 以及发送电子邮件时的发件人地址
     *
     * @var string
     */
    protected $oauthUserEmail = '';

    /**
     * 客户端密码，在您要连接的服务的应用程序定义中生成
     *
     * @var string
     */
    protected $oauthClientSecret = '';

    /**
     * 客户端ID，在您要连接的服务的应用程序定义中生成
     *
     * @var string
     */
    protected $oauthClientId = '';

    /**
     * 刷新令牌，用于获取新的AccessTokens
     *
     * @var string
     */
    protected $oauthRefreshToken = '';

    /**
     * OAuth初始化
     *
     * @param array $options 包含`provider`, `userName`, `clientSecret`, `clientId` and `refreshToken` 元素
     */
    public function __construct($options)
    {
        $this->provider = $options['provider'];
        $this->oauthUserEmail = $options['userName'];
        $this->oauthClientSecret = $options['clientSecret'];
        $this->oauthClientId = $options['clientId'];
        $this->oauthRefreshToken = $options['refreshToken'];
    }

    /**
     * 获取新的 RefreshToken
     *
     * @return RefreshToken
     */
    protected function getGrant()
    {
        return new RefreshToken();
    }

    /**
     * 获取一个新的AccessToken.
     *
     * @return AccessToken
     */
    protected function getToken()
    {
        return $this->provider->getAccessToken($this->getGrant(), ['refresh_token' => $this->oauthRefreshToken]);
    }

    /**
     * 生成 base64 编码的 OAuth 令牌
     *
     * @return string
     */
    public function getOauth64()
    {
        //如果令牌不可用或已过期，重新获取新令牌
        if (null === $this->oauthToken || $this->oauthToken->hasExpired()) {
            $this->oauthToken = $this->getToken();
        }

        return base64_encode(
            'user=' .
            $this->oauthUserEmail .
            "\001auth=Bearer " .
            $this->oauthToken .
            "\001\001"
        );
    }
}
