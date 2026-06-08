<?php

namespace Drupal\Tests\ckeditor_ai_agent\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests access control for CKEditor AI Agent endpoints.
 *
 * @group ckeditor_ai_agent
 */
class AiChatProxyAccessTest extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'ckeditor_ai_agent',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests anonymous users cannot access the settings page.
   */
  public function testSettingsPageDeniedForAnonymous(): void {
    $this->drupalGet('/admin/config/content/ckeditor-ai-agent');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests users without permission cannot access settings.
   */
  public function testSettingsPageDeniedWithoutPermission(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/config/content/ckeditor-ai-agent');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests admin users can access the settings page.
   */
  public function testSettingsPageAccessForAdmin(): void {
    $admin = $this->drupalCreateUser(['administer ckeditor ai agent']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/content/ckeditor-ai-agent');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('CKEditor AI Agent Settings');
  }

  /**
   * Tests the chat proxy denies anonymous POST requests.
   */
  public function testChatEndpointDeniedForAnonymous(): void {
    $this->assertEquals(403, $this->postToChat());
  }

  /**
   * Tests the chat proxy denies users without the required permission.
   */
  public function testChatEndpointDeniedWithoutPermission(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);
    $this->assertEquals(403, $this->postToChat());
  }

  /**
   * Tests the chat proxy requires a valid CSRF token.
   */
  public function testChatEndpointDeniedWithoutCsrfToken(): void {
    $user = $this->drupalCreateUser(['use ckeditor ai agent']);
    $this->drupalLogin($user);
    $this->assertEquals(403, $this->postToChat());
  }

  /**
   * Tests the chat proxy grants access with permission and CSRF token.
   *
   * No AI provider is configured in the test environment so the controller
   * returns a non-403 error; the important assertion is that access control
   * (permission + CSRF) did not block the request.
   */
  public function testChatEndpointAccessForPermittedUser(): void {
    $user = $this->drupalCreateUser(['use ckeditor ai agent']);
    $this->drupalLogin($user);

    $path = Url::fromRoute('ckeditor_ai_agent.ai_chat')->getInternalPath();
    $token = \Drupal::csrfToken()->get($path);

    $status = $this->postToChat([
      'messages' => [['role' => 'user', 'content' => 'Hello']],
    ], $token);
    $this->assertNotEquals(403, $status, 'Permitted user with valid CSRF token should pass access checks.');
  }

  /**
   * Tests hook_requirements reports AI provider status.
   */
  public function testStatusReportShowsProviderCheck(): void {
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('CKEditor AI Agent');
  }

  /**
   * Posts to the chat proxy endpoint and returns the HTTP status code.
   *
   * @param array $body
   *   The JSON request body.
   * @param string|null $csrf_token
   *   An optional CSRF token to include as a query parameter.
   *
   * @return int
   *   The HTTP response status code.
   */
  protected function postToChat(array $body = [], ?string $csrf_token = NULL): int {
    $url = $this->buildUrl('/ckeditor-ai-agent/ai/chat');
    if ($csrf_token !== NULL) {
      $url .= '?token=' . urlencode($csrf_token);
    }

    /** @var \Behat\Mink\Driver\BrowserKitDriver $driver */
    $driver = $this->getSession()->getDriver();
    $client = $driver->getClient();
    $client->request('POST', $url, [], [], [
      'CONTENT_TYPE' => 'application/json',
    ], json_encode($body));

    return $client->getInternalResponse()->getStatusCode();
  }

}
