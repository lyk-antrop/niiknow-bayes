# `bayes` — Naive-Bayes Classifier for PHP

A Naive-Bayes text classifier. Takes a document (piece of text or a pre-tokenized object) and
tells you what category it belongs to.

Originally ported from the Node.js library [ttezel/bayes](https://github.com/ttezel/bayes).
This is a **local vendor fork** with significant architectural improvements over the
[upstream niiknow/bayes](https://github.com/niiknow/bayes).

## What can I use this for?

Categorize any text content into arbitrary categories:

- spam detection
- sentiment analysis (positive / negative)
- topic classification (technology / politics / sports)
- multi-dimension tagging (e.g. per-attribute Bayes classifiers)

## Requirements

- PHP 8.3+
- No external dependencies

## Fork improvements over upstream

### Tokenizer separation

The upstream library couples tokenization to the classifier via a closure passed in an options
array. This fork introduces a clean architecture:

- **`TokenizerInterface`** — contract for tokenizers: `tokenize(string|TokenizableInterface $input): array`
- **`AbstractTokenizer`** — base class that dispatches to `tokenizeString()` for strings and
  delegates to the object's own `tokenize()` method for `TokenizableInterface` inputs
- **`TokenizableInterface`** — objects that know how to tokenize themselves. This allows
  domain objects (e.g. a profile facade) to implement custom tokenization logic without
  the classifier knowing anything about domain structure
- **`DefaultTokenizer`** — simple word splitter (`/[[:alpha:]]+/u`), same behaviour as upstream

The tokenizer is injected via constructor: `new Bayes(new MyCustomTokenizer())`.

### Weighted learning

`learn()` accepts an optional `float $weight` parameter (default `1.0`):

```php
$classifier->learn($text, 'category', weight: 0.7);
```

The weight scales all four internal counters (`docCount`, `totalDocuments`, `wordCount`,
`wordFrequencyCount`) multiplicatively. This allows:

- **Source weighting** — give live confirmed data full weight, archived/stale data reduced weight
- **Conformance weighting** — boost records that disagree with the model's current prediction
  (they carry the most new signal)
- **Any multiplicative scheme** — compose multiple weight factors before passing to `learn()`

Float counters are rounded to 6 decimal places on serialization to prevent noise accumulation.

### Probability output formats

`probabilities()` accepts a `ProbabilityFormat` enum:

```php
$classifier->probabilities($text, ProbabilityFormat::LOG);        // raw log probabilities (default)
$classifier->probabilities($text, ProbabilityFormat::PROBABILITY); // normalized 0..1
$classifier->probabilities($text, ProbabilityFormat::PERCENTAGE);  // normalized 0..100
```

Conversion uses the log-sum-exp trick to prevent underflow.

### Vocabulary pruning

```php
$classifier->prune(minFrequency: 3);
```

Removes tokens that appear fewer than `minFrequency` times across all categories. Reduces
model size and can improve generalization by eliminating noise tokens.

### Interface-driven design

`ClassifierInterface` defines the full public contract: `learn()`, `categorize()`,
`probabilities()`, `toJson()`, `fromJson()`, `reset()`, `prune()`. All type-hinted with
union types (`string|TokenizableInterface`) and enums.

### Other improvements

- `declare(strict_types=1)` throughout
- PHP 8.3 minimum (constructor promotion, enums, readonly, union types)
- PHPStan clean at max level
- JSON round-trip preserves float precision via controlled rounding
- Word frequency counts sorted by frequency (descending) after each `learn()` call

## Usage

```php
use Niiknow\Bayes;
use Niiknow\ProbabilityFormat;

$classifier = new Bayes();

// teach it
$classifier->learn('amazing, awesome movie!! Yeah!! Oh boy.', 'positive');
$classifier->learn('Sweet, this is incredibly, amazing, perfect, great!!', 'positive');
$classifier->learn('terrible, shitty thing. Damn. Sucks!!', 'negative');

// classify
$classifier->categorize('awesome, cool, amazing!! Yay.');
// => 'positive'

// get probability distribution
$classifier->probabilities('awesome, cool, amazing!! Yay.', ProbabilityFormat::PERCENTAGE);
// => ['positive' => 87.3, 'negative' => 12.7]

// weighted learning
$classifier->learn($archivedText, 'positive', weight: 0.5);

// serialize / deserialize
$json = $classifier->toJson();
$classifier->fromJson($json);
```

### Custom tokenizer

```php
use Niiknow\AbstractTokenizer;

class StopwordTokenizer extends AbstractTokenizer
{
    private array $stopwords = ['der', 'die', 'das', 'the', 'a', 'an'];

    protected function tokenizeString(string $text, mixed $tokenizerArgument = null): array
    {
        preg_match_all('/[[:alpha:]]+/u', mb_strtolower($text), $matches);
        return array_values(array_diff($matches[0], $this->stopwords));
    }
}

$classifier = new Bayes(new StopwordTokenizer());
```

### Pre-tokenized objects

```php
use Niiknow\TokenizableInterface;

class ProfileFacade implements TokenizableInterface
{
    public function tokenize(mixed $tokenizerArgument = null): array
    {
        // domain-specific tokenization: combine description tokens,
        // service codes, location indicators, etc.
        return [...$this->descriptionTokens, ...$this->serviceTokens];
    }
}

$classifier->learn(new ProfileFacade($profile), 'category');
```

## API

### `new Bayes(?TokenizerInterface $tokenizer = new DefaultTokenizer())`

Creates a classifier instance with the given tokenizer.

### `learn(string|TokenizableInterface $input, string $category, float $weight = 1.0): self`

Train the classifier. Accepts raw text (tokenized by the injected tokenizer) or a
`TokenizableInterface` object (tokenizes itself).

### `categorize(string|TokenizableInterface $input): ?string`

Returns the most likely category, or `null` if the classifier has no training data.

### `probabilities(string|TokenizableInterface $input, ProbabilityFormat $format = LOG): ?array`

Returns an associative array of category => probability in the requested format.

### `toJson(): string` / `fromJson(array|string $json): self`

Serialize and restore classifier state. JSON format is compatible with the upstream
niiknow/bayes library (when weight = 1.0).

### `reset(): self`

Clear all training data.

### `prune(int $minFrequency): self`

Remove low-frequency tokens from the model vocabulary.

## License

MIT

