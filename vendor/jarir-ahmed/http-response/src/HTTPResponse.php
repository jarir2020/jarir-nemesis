<?php

namespace JarirAhmed\HTTPResponse;

class HTTPResponse
{
    public static function code($statusCode)
    {
        http_response_code($statusCode);
    }

    public static function continue()
    {
        self::code(100);
    }

    public static function switchingProtocols()
    {
        self::code(101);
    }

    public static function ok()
    {
        self::code(200);
    }

    public static function created()
    {
        self::code(201);
    }

    public static function accepted()
    {
        self::code(202);
    }

    public static function noContent()
    {
        self::code(204);
    }

    public static function resetContent()
    {
        self::code(205);
    }

    public static function partialContent()
    {
        self::code(206);
    }

    public static function movedPermanently()
    {
        self::code(301);
    }

    public static function found()
    {
        self::code(302);
    }

    public static function seeOther()
    {
        self::code(303);
    }

    public static function notModified()
    {
        self::code(304);
    }

    public static function temporaryRedirect()
    {
        self::code(307);
    }

    public static function permanentRedirect()
    {
        self::code(308);
    }

    public static function badRequest()
    {
        self::code(400);
    }

    public static function unauthorized()
    {
        self::code(401);
    }

    public static function paymentRequired()
    {
        self::code(402);
    }

    public static function forbidden()
    {
        self::code(403);
    }

    public static function notFound()
    {
        self::code(404);
    }

    public static function methodNotAllowed()
    {
        self::code(405);
    }

    public static function notAcceptable()
    {
        self::code(406);
    }

    public static function proxyAuthenticationRequired()
    {
        self::code(407);
    }

    public static function requestTimeout()
    {
        self::code(408);
    }

    public static function conflict()
    {
        self::code(409);
    }

    public static function gone()
    {
        self::code(410);
    }

    public static function lengthRequired()
    {
        self::code(411);
    }

    public static function preconditionFailed()
    {
        self::code(412);
    }

    public static function payloadTooLarge()
    {
        self::code(413);
    }

    public static function uriTooLong()
    {
        self::code(414);
    }

    public static function unsupportedMediaType()
    {
        self::code(415);
    }

    public static function rangeNotSatisfiable()
    {
        self::code(416);
    }

    public static function expectationFailed()
    {
        self::code(417);
    }

    public static function imTeapot()
    {
        self::code(418);
    }

    public static function misdirectedRequest()
    {
        self::code(421);
    }

    public static function unprocessableEntity()
    {
        self::code(422);
    }

    public static function locked()
    {
        self::code(423);
    }

    public static function failedDependency()
    {
        self::code(424);
    }

    public static function tooEarly()
    {
        self::code(425);
    }

    public static function upgradeRequired()
    {
        self::code(426);
    }

    public static function preconditionRequired()
    {
        self::code(428);
    }

    public static function tooManyRequests()
    {
        self::code(429);
    }

    public static function requestHeaderFieldsTooLarge()
    {
        self::code(431);
    }

    public static function unavailableForLegalReasons()
    {
        self::code(451);
    }

    public static function internalServerError()
    {
        self::code(500);
    }

    public static function notImplemented()
    {
        self::code(501);
    }

    public static function badGateway()
    {
        self::code(502);
    }

    public static function serviceUnavailable()
    {
        self::code(503);
    }

    public static function gatewayTimeout()
    {
        self::code(504);
    }

    public static function httpVersionNotSupported()
    {
        self::code(505);
    }

    public static function variantAlsoNegotiates()
    {
        self::code(506);
    }

    public static function insufficientStorage()
    {
        self::code(507);
    }

    public static function loopDetected()
    {
        self::code(508);
    }

    public static function notExtended()
    {
        self::code(510);
    }

    public static function networkAuthenticationRequired()
    {
        self::code(511);
    }
}
