<?php

use App\Models\User;

/**
 * T10 — auth pages have a heading and the right register/login link.
 *
 * Wave 0b (A2, A3): each of the six auth pages gets an <h1> with
 * data-testid="auth-heading" and the exact text in the spec. /login
 * adds a visible "Create account" link to route('register') with
 * data-testid="login-register-link". /register keeps its existing
 * link to route('login') and gives it data-testid="register-login-link".
 *
 * The page must contain EXACTLY ONE element with
 * data-testid="auth-heading" and that element must be an <h1>.
 *
 * Would fail if:
 *   - the auth-heading is missing, doubled, or sits on a non-h1 element,
 *   - the heading text drifts from the spec,
 *   - the register / login cross-link is missing or its href drifts.
 */

function authHeadingHtml(string $body): array
{
    // Count of data-testid="auth-heading" must be exactly 1, and that
    // element must be an <h1>. Return both the tag name and the text
    // so the caller can assert on it.
    $pattern = '/<(h1|h2|h3|h4|h5|h6|div|p|span)\b[^>]*\bdata-testid="auth-heading"[^>]*>(.*?)<\/\1>/is';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 element with data-testid=\"auth-heading\", found {$found}.");

    return [
        'tag' => strtolower($matches[1][0]),
        'text' => trim(strip_tags(html_entity_decode($matches[2][0], ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
    ];
}

test('T10a: /login has exactly one <h1> auth-heading with text "Log in"', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Log in');
});

test('T10b: /register has exactly one <h1> auth-heading with text "Create account"', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Create account');
});

test('T10c: /forgot-password has exactly one <h1> auth-heading with text "Forgot your password"', function () {
    $response = $this->get('/forgot-password');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Forgot your password');
});

test('T10d: /reset-password/<token> (guest) has exactly one <h1> auth-heading with text "Set a new password"', function () {
    $response = $this->get('/reset-password/anytoken');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Set a new password');
});

test('T10e: /confirm-password has exactly one <h1> auth-heading with text "Confirm your password"', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/confirm-password');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Confirm your password');
});

test('T10f: /verify-email (unverified) has exactly one <h1> auth-heading with text "Verify your email"', function () {
    $user = User::factory()->unverified()->create();
    $response = $this->actingAs($user)->get('/verify-email');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $h = authHeadingHtml($body);
    expect($h['tag'])->toBe('h1');
    expect($h['text'])->toBe('Verify your email');
});

test('T10g: /login has a <a data-testid="login-register-link"> to route(register) with text containing "Create account"', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $pattern = '/<a\b[^>]*\bdata-testid="login-register-link"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 <a data-testid=\"login-register-link\">, found {$found}.");

    $tag = $matches[0][0];
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "login-register-link <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('register'));

    // Inner text must contain "Create account".
    $innerPattern = '/<a\b[^>]*\bdata-testid="login-register-link"[^>]*>(.*?)<\/a>/is';
    $innerMatches = [];
    $hasInner = preg_match($innerPattern, $body, $innerMatches);
    expect($hasInner)->toBe(1, "Could not capture inner text of login-register-link.");
    $innerText = trim(strip_tags(html_entity_decode($innerMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    expect($innerText)->toContain('Create account');
});

test('T10h: /register has a <a data-testid="register-login-link"> to route(login)', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
    $body = (string) $response->getContent();

    $pattern = '/<a\b[^>]*\bdata-testid="register-login-link"[^>]*>/i';
    $matches = [];
    $found = preg_match_all($pattern, $body, $matches);
    expect($found)->toBe(1, "Expected exactly 1 <a data-testid=\"register-login-link\">, found {$found}.");

    $tag = $matches[0][0];
    $hrefPattern = '/\bhref=(["\'])(.*?)\1/i';
    $hrefMatches = [];
    $hasHref = preg_match($hrefPattern, $tag, $hrefMatches);
    expect($hasHref)->toBe(1, "register-login-link <a> has no href attribute. Tag was: {$tag}");
    expect($hrefMatches[2])->toBe(route('login'));
});