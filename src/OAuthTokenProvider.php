<?php
namespace Yng\Mailer;

/**
 * OAuthTokenProvider - OAuth2 令牌提供者接口
 * 为 SMTP 身份验证提供 base64 编码的 OAuth2 身份验证字符串
 */
interface OAuthTokenProvider
{
    /**
     * 生成 base64 编码的 OAuth 令牌，确保访问令牌未过期。
     * 要进行 base 64 编码的字符串应采用以下形式：“user=<user_email_address>\001auth=Bearer <access_token>\001\001”
     *
     * @return string
     */
    public function getOauth64();
}
