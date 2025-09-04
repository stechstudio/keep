<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\Settings;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Services\TemplateService;
use Tests\Fixtures\TestVault;

class TemplateServiceTest extends TestCase
{
    protected string $tempDir;
    protected TemplateService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary directory for templates
        $this->tempDir = sys_get_temp_dir() . '/keep_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Create template service with temp directory
        $this->service = new TemplateService($this->tempDir);
    }
    
    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    protected function recursiveRemoveDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveRemoveDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    public function test_get_template_path(): void
    {
        $this->assertEquals($this->tempDir, $this->service->getTemplatePath());
    }
    
    public function test_template_exists(): void
    {
        $this->assertFalse($this->service->templateExists('production'));
        
        // Create a template file
        file_put_contents($this->tempDir . '/production.env', 'TEST=value');
        
        $this->assertTrue($this->service->templateExists('production'));
        $this->assertFalse($this->service->templateExists('staging'));
    }
    
    public function test_get_template_filename(): void
    {
        $this->assertEquals('production.env', $this->service->getTemplateFilename('production'));
        $this->assertEquals('staging.env', $this->service->getTemplateFilename('staging'));
        $this->assertEquals('local.env', $this->service->getTemplateFilename('local'));
    }
    
    public function test_normalize_key_to_env(): void
    {
        $this->assertEquals('DB_PASSWORD', $this->service->normalizeKeyToEnv('db-password'));
        $this->assertEquals('API_KEY', $this->service->normalizeKeyToEnv('api-key'));
        $this->assertEquals('API_KEY', $this->service->normalizeKeyToEnv('API_KEY'));
        $this->assertEquals('APIKEY', $this->service->normalizeKeyToEnv('apiKey'));
        $this->assertEquals('SOME_DOTTED_KEY', $this->service->normalizeKeyToEnv('some.dotted.key'));
        $this->assertEquals('KEY_WITH_SPACES', $this->service->normalizeKeyToEnv('key with spaces'));
    }
    
    public function test_save_template(): void
    {
        $content = "TEST_KEY={vault:test_key}\nANOTHER_KEY={vault:another_key}";
        $filepath = $this->service->saveTemplate('testing', $content);
        
        $this->assertFileExists($filepath);
        $this->assertEquals($this->tempDir . '/testing.env', $filepath);
        $this->assertEquals($content, file_get_contents($filepath));
    }
    
    public function test_save_template_creates_directory_if_not_exists(): void
    {
        $newTempDir = $this->tempDir . '/nested/path';
        $service = new TemplateService($newTempDir);
        
        $content = "TEST=value";
        $filepath = $service->saveTemplate('test', $content);
        
        $this->assertDirectoryExists($newTempDir);
        $this->assertFileExists($filepath);
    }
    
    public function test_scan_templates(): void
    {
        // Create some template files
        file_put_contents($this->tempDir . '/production.env', 'PROD=value');
        file_put_contents($this->tempDir . '/staging.env', 'STAGE=value');
        file_put_contents($this->tempDir . '/other.txt', 'not a template');
        
        $templates = $this->service->scanTemplates();
        
        $this->assertCount(2, $templates);
        
        // Check first template
        $prodTemplate = array_filter($templates, fn($t) => $t['filename'] === 'production.env');
        $this->assertCount(1, $prodTemplate);
        $prodTemplate = array_values($prodTemplate)[0];
        
        $this->assertEquals('production.env', $prodTemplate['filename']);
        $this->assertEquals('production', $prodTemplate['stage']);
        $this->assertEquals(10, $prodTemplate['size']); // "PROD=value" is 10 bytes
        $this->assertIsInt($prodTemplate['lastModified']);
        
        // Check second template
        $stageTemplate = array_filter($templates, fn($t) => $t['filename'] === 'staging.env');
        $this->assertCount(1, $stageTemplate);
        $stageTemplate = array_values($stageTemplate)[0];
        
        $this->assertEquals('staging.env', $stageTemplate['filename']);
        $this->assertEquals('staging', $stageTemplate['stage']);
    }
    
    public function test_scan_templates_returns_empty_array_for_missing_directory(): void
    {
        $service = new TemplateService('/non/existent/path');
        $templates = $service->scanTemplates();
        
        $this->assertIsArray($templates);
        $this->assertEmpty($templates);
    }
    
    public function test_load_template(): void
    {
        $content = "# Test template\nDB_HOST={vault:db_host}\nDB_PASSWORD={vault:db_password}";
        file_put_contents($this->tempDir . '/test.env', $content);
        
        $template = $this->service->loadTemplate('test.env');
        
        $this->assertInstanceOf(\STS\Keep\Data\Template::class, $template);
        
        // Verify template content by checking if it can extract placeholders
        $placeholders = $template->placeholders();
        $this->assertCount(2, $placeholders);
    }
    
    public function test_load_template_throws_exception_for_missing_file(): void
    {
        $this->expectException(KeepException::class);
        $this->expectExceptionMessage('Template file not found: missing.env');
        
        $this->service->loadTemplate('missing.env');
    }
    
    public function test_generate_template_section_formatting(): void
    {
        // Create a mock service to test protected method
        $service = new class($this->tempDir) extends TemplateService {
            public function testGenerateVaultSection(string $vaultName, SecretCollection $secrets): string
            {
                return $this->generateVaultSection($vaultName, $secrets);
            }
        };
        
        $secrets = new SecretCollection([
            new Secret('db-password', 'secret123', null, true, null),
            new Secret('api_key', 'key456', null, true, null),
            new Secret('APP_URL', 'https://example.com', null, false, null),
        ]);
        
        $section = $service->testGenerateVaultSection('test-vault', $secrets);
        
        $expectedSection = "# ===== Vault: test-vault =====\n";
        $expectedSection .= "DB_PASSWORD={test-vault:db-password}\n";
        $expectedSection .= "API_KEY={test-vault:api_key}\n";
        $expectedSection .= "APP_URL={test-vault:APP_URL}\n";
        $expectedSection .= "";
        
        $this->assertEquals($expectedSection, $section);
    }
    
    public function test_generate_template_header_and_footer(): void
    {
        // Create a mock service to test protected methods
        $service = new class($this->tempDir) extends TemplateService {
            public function testGenerateTemplateHeader(string $stage): string
            {
                return $this->generateTemplateHeader($stage);
            }
            
            public function testGenerateNonSecretSection(): string
            {
                return $this->generateNonSecretSection();
            }
        };
        
        $header = $service->testGenerateTemplateHeader('production');
        $this->assertStringContainsString('# Keep Template - Stage: production', $header);
        $this->assertStringContainsString('# Generated:', $header);
        
        $nonSecret = $service->testGenerateNonSecretSection();
        $this->assertStringContainsString('# ===== Application Settings (non-secret) =====', $nonSecret);
        $this->assertStringContainsString('# APP_NAME=MyApp', $nonSecret);
        $this->assertStringContainsString('# APP_DEBUG=false', $nonSecret);
    }
}