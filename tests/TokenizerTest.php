<?php

declare(strict_types=1);

namespace Niiknow\Tests;

use Niiknow\DefaultTokenizer;
use Niiknow\TokenizableInterface;
use Niiknow\TokenizerInterface;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    private TokenizerInterface $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new DefaultTokenizer();
    }

    /**
     * Test basic tokenization functionality
     */
    public function testTokenize(): void
    {
        // Test with a simple string
        $text = 'Hello world!';
        $tokens = $this->tokenizer->tokenize($text);
        
        $this->assertEquals(['hello', 'world'], $tokens);
        
        // Test with mixed case and punctuation
        $text = 'Hello, World! This is a TEST.';
        $tokens = $this->tokenizer->tokenize($text);
        
        $this->assertEquals(['hello', 'world', 'this', 'is', 'a', 'test'], $tokens);
        
        // Test with numbers and special characters
        $text = 'Testing123 with $pecial ch@racters';
        $tokens = $this->tokenizer->tokenize($text);
        
        $this->assertEquals(['testing', 'with', 'pecial', 'ch', 'racters'], $tokens);
    }

    /**
     * Test tokenization with different languages
     */
    public function testTokenizeMultilingualText(): void
    {
        // Test with non-English characters
        $text = 'Hola cómo estás';
        $tokens = $this->tokenizer->tokenize($text);
        
        $this->assertEquals(['hola', 'cómo', 'estás'], $tokens);
    }

    /**
     * Test tokenization with empty string
     */
    public function testTokenizeEmptyString(): void
    {
        $text = '';
        $tokens = $this->tokenizer->tokenize($text);
        
        $this->assertEmpty($tokens);
    }

    /**
     * Create a tokenizable object with the given content
     */
    private function createTokenizable(string $content): TokenizableInterface
    {
        return new class($content) implements TokenizableInterface {

            public function __construct(private readonly string $content) {}
            
            public function tokenize(): array
            {
                $matches = [];
                preg_match_all('/[[:alpha:]]+/u', mb_strtolower($this->content), $matches);

                return $matches[0];
            }
        };
    }

    /**
     * Test tokenization with a custom TokenizableInterface object
     */
    public function testTokenizeCustomObject(): void
    {
        // Create a custom object with simple content
        $customObject = $this->createTokenizable('Hello tokenizable world');
        $tokens = $this->tokenizer->tokenize($customObject);
        
        $this->assertEquals(['hello', 'tokenizable', 'world'], $tokens);
        
        // Test with more complex content
        $customObject = $this->createTokenizable('Multiple Words, with Punctuation!');
        $tokens = $this->tokenizer->tokenize($customObject);
        
        $this->assertEquals(['multiple', 'words', 'with', 'punctuation'], $tokens);
    }
    
    /**
     * Test tokenization with empty TokenizableInterface object
     */
    public function testTokenizeEmptyCustomObject(): void
    {
        $customObject = $this->createTokenizable('');
        $tokens = $this->tokenizer->tokenize($customObject);
        
        $this->assertEmpty($tokens);
    }
    
    /**
     * Test tokenization with multilingual TokenizableInterface object
     */
    public function testTokenizeMultilingualCustomObject(): void
    {
        $customObject = $this->createTokenizable('Hablamos español y English too');
        $tokens = $this->tokenizer->tokenize($customObject);
        
        $this->assertEquals(['hablamos', 'español', 'y', 'english', 'too'], $tokens);
    }
}
