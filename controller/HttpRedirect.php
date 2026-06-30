<?php

/**
 * HttpRedirect
 *
 * Centralises the "redirect then terminate the request" pattern used by the
 * controllers. In normal runtime this behaves exactly as the previous inline
 * `header('Location: ...'); exit;` code did: it sends the Location header and
 * terminates the script.
 *
 * Under the testing environment (APP_ENV=testing, set via phpunit.xml) it
 * instead throws a catchable RedirectException so controller tests can drive a
 * full request flow and then assert on session/flash state, without the test
 * process being killed by a bare `exit`.
 */

if (!class_exists('RedirectException')) {
    /**
     * Thrown in place of `exit` during a redirect when running under tests.
     * Extends RuntimeException so existing `catch (\Exception $e)` blocks in the
     * controller tests intercept it.
     */
    class RedirectException extends RuntimeException
    {
        public readonly string $location;

        public function __construct(string $location)
        {
            $this->location = $location;
            parent::__construct('Redirect to: ' . $location);
        }
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Send a Location header and terminate the request.
     *
     * @param string $location  The value for the Location header (e.g. "index.php?page=login").
     *
     * @throws RedirectException When APP_ENV=testing, instead of calling exit.
     */
    function redirectTo(string $location): void
    {
        // Avoid "headers already sent" warnings when output has already started
        // (e.g. under the CLI test runner). In a normal web request no output
        // precedes the redirect, so the header is sent as before.
        if (!headers_sent()) {
            header('Location: ' . $location);
        }

        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '';

        if ($appEnv === 'testing') {
            throw new RedirectException($location);
        }

        exit;
    }
}
