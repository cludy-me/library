<?php namespace October\Rain\Html;

class HtmlFormatter
{
    use \Illuminate\Support\Traits\Macroable;

    const
        SPACES_INDENTATION = 'spacesIndentation',
        TABS_INDENTATION = 'tabsIndentation',

        CONTENT_HTML = 'contentHtml',
        CONTENT_XHTML = 'contentXhtml',
        CONTENT_XML = 'contentXml',

        TOKEN_OPEN_TAG = 'openTag',
        TOKEN_UNPAIRED_TAG = 'unpairedTag',
        TOKEN_CLOSE_TAG = 'closeTag',
        TOKEN_TEXT = 'text',

        SKIP_TAG = 'formatter-off',

        OPEN_TAG_RE = '/<\??(?<element>[-\w]+)(?:[^>]+)?>/',
        CLOSE_TAG_RE = '/^<\/[^\>]+>/',
        PREG_SPLIT_RE = '/(<(?:\/|\?)?[-\w]+(?:>|.*?>))/',
        FORMATTER_OFF_REMOVE_RE = '/<\/?' . self::SKIP_TAG . '>(?:(?<!<\/' . self::SKIP_TAG . '>))?/m',

        CODE_PLACEHOLDER_NAMESPACE_PREFIX = 'codePlaceholder_',
        CODE_PLACEHOLDER_RE = '/' . self::CODE_PLACEHOLDER_NAMESPACE_PREFIX . '\d+/';

    /**
     * @var array
     */
    private $codePlaceholders = [];

    /**
     * @var array
     */
    private $codePlaceholdersRegularExpressions = [];

    /**
     * @var string
     */
    private $contentType = self::CONTENT_HTML;

    /**
     * @var string
     */
    private $output;

    /**
     * @var string
     */
    private $outputIndentation;

    /**
     * @var string
     */
    private $outputIndentationMethod = self::TABS_INDENTATION;

    /**
     * @var int
     */
    private $outputIndentationSize;

    /**
     * @var string
     */
    private $outputIndentationUnit;

    /**
     * @var string
     */
    private $previousTokenType;

    /**
     * @var array
     */
    private $skippedElements = [self::SKIP_TAG, 'code', 'script', 'style', 'textarea'];

    /**
     * @var array
     */
    private $unpairedElements = [
        self::CONTENT_HTML => [
            'area', 'base', 'br', 'code', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
            // svg
            'circle', 'animateTransform'
        ],

        self::CONTENT_XML => [
            'cloud', 'xml'
        ]
    ];

    /**
     * @var bool
     */
    private $xmlSyntax = FALSE;
    
    public function isHtml($string)
    {
        return (strpos($string, '<!doctype html>') !== false);
    }

    /**
     * @param string|array $skippedElement
     * @return HtmlFormatter
     */
    public function addSkippedElement($skippedElement): self
    {
        if (is_string($skippedElement))
            $skippedElement = explode(' ', $skippedElement);

        $skippedElements = $this->skippedElements;
        $this->skippedElements = array_unique(array_merge($skippedElements, $skippedElement));

        return $this;
    }

    /**
     * @param string|array $elements
     * @param string|NULL $contentType
     * @return HtmlFormatter
     */
    public function addUnpairedElements($elements, string $contentType = NULL): self
    {
        if (is_string($elements))
            $elements = explode(' ', trim($elements));

        $contentType = $contentType ?? self::CONTENT_HTML;

        foreach ($elements as $element)
            $this->unpairedElements[$contentType][] = $element;

        return $this;
    }

    public function format(string $string)
    {
        $this->output = '';
        $this->outputIndentation = '';
        $this->outputIndentationUnit = $this->outputIndentationMethod === self::TABS_INDENTATION
            ? "\t"
            : str_repeat(' ', $this->outputIndentationSize);

        if (app()->environment() == 'production')
            $string = preg_replace('/<!--(.|\s)*?-->/', '', $string);

        $string = preg_replace('/[\t\n\r\0\x0B]/', '', $string);
        $string = preg_replace('/([\s])\1+/', ' ', $string);
        
        $this->setCodePlaceholdersRegularExpressions();
        $string = $this->setCodePlaceholders($string);

        $tokens = preg_split(
            self::PREG_SPLIT_RE,
            $string,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        foreach ($tokens as $token)
            $this->formatToken($token);

        $this->unsetCodePlaceholders();
        $this->removeBlankLines();
        $this->addBlankLine();

        return $this->output;
    }

    public function setContentType(string $contentType): self
    {
        $this->xmlSyntax = in_array($contentType, [self::CONTENT_XHTML, self::CONTENT_XML], TRUE);

        if ($contentType === self::CONTENT_XHTML)
            $contentType = self::CONTENT_HTML;

        $this->contentType = $contentType;

        return $this;
    }

    public function setSpacesIndentationMethod(int $indentationSize): self
    {
        $this->outputIndentationMethod = self::SPACES_INDENTATION;
        $this->outputIndentationSize = $indentationSize;

        return $this;
    }

    private function addBlankLine(): self
    {
        if (!preg_match("/\n$/", $this->output))
            $this->output .= "\n";

            return $this;
    }

    private function decreaseIndentation(): self
    {
        $this->outputIndentation = preg_replace(
            "/" . $this->outputIndentationUnit . "/",
            '',
            $this->outputIndentation,
            1
        ) ?? '';

        return $this;
    }

    private function formatToken(string $token)
    {
        $token = trim($token);

        if (!$token)
            return;

        $type = $this->matchTokenType($token);
        $previousTokenIsOpenTag = $this->previousTokenType === self::TOKEN_OPEN_TAG;
        $previousTokenIsText = $this->previousTokenType === self::TOKEN_TEXT;
        $tokenIsOpenTag = $type === self::TOKEN_OPEN_TAG;
        $tokenIsUnpairedTag = $type === self::TOKEN_UNPAIRED_TAG;
        $tokenIsCloseTag = $type === self::TOKEN_CLOSE_TAG;
        $tokenIsText = $type === self::TOKEN_TEXT;
        $emptyElement = $tokenIsCloseTag && $previousTokenIsOpenTag;
        $connectedText = $tokenIsText && $previousTokenIsText;

        if ($previousTokenIsOpenTag && ($tokenIsOpenTag || $tokenIsUnpairedTag || $tokenIsText)) {
            $this->increaseIndentation();
        } elseif ($tokenIsCloseTag && !$previousTokenIsOpenTag) {
            $this->decreaseIndentation();
        }

        if (!(!$this->output || $connectedText || $emptyElement)) {
            $this->output .= "\n";
        }

        if (!(!$token || $emptyElement || $connectedText)) {
            $this->output .= $this->outputIndentation;
        }

        $this->output .= $token;
        $this->previousTokenType = $type;
    }

    private function increaseIndentation(): self
    {
        $this->outputIndentation .= $this->outputIndentationUnit;

        return $this;
    }

    private function matchCloseTag(string $string): bool
    {
        return (bool) preg_match(self::CLOSE_TAG_RE, $string);
    }

    private function matchOpenTag(string $string, array &$matches = NULL): bool
    {
        return (bool) preg_match(self::OPEN_TAG_RE, $string, $matches);
    }

    private function matchTokenType(string $token): string
    {
        $type = self::TOKEN_TEXT;

        if ($this->matchOpenTag($token, $matches))
            $type = in_array($matches['element'], $this->unpairedElements[$this->contentType])
                ? self::TOKEN_UNPAIRED_TAG
                : self::TOKEN_OPEN_TAG;
        elseif ($this->matchCloseTag($token))
            $type = self::TOKEN_CLOSE_TAG;

        return $type;
    }

    private function removeBlankLines(): self
    {
        $this->output = preg_replace('/\n\s*\n/', "\n", $this->output);

        return $this;
    }

    private function setCodePlaceholders(string $string): string
    {
        foreach ($this->codePlaceholdersRegularExpressions as $codePlaceholderRe) {
            preg_match_all($codePlaceholderRe, $string, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $fullMatch = $match[0];
                $fullMatchReplacement = $fullMatch;
                $codeToReplace = end($match);

                if (!trim($codeToReplace))
                    continue;

                $codePlaceholder = uniqid(self::CODE_PLACEHOLDER_NAMESPACE_PREFIX);
                $fullMatchReplacement = str_replace($codeToReplace, $codePlaceholder, $fullMatchReplacement);
                $string = preg_replace(
                    '/' . preg_quote($fullMatch, '/') . '/',
                    $fullMatchReplacement,
                    $string,
                    1
                );

                $this->codePlaceholders[$codePlaceholder] = $codeToReplace;
            }
        }

        return $string;
    }

    private function setCodePlaceholdersRegularExpressions(): self
    {
        $skippedElements = join('|', $this->skippedElements);

        $this->codePlaceholdersRegularExpressions = [
            '/<\?php(?: |\n)(?:.|\n)*\?>/Um', // php code
            '/[\w\:-]+=(?:"([^"]*)"|\'([^\']*)\'|([^ >]*))/', // element attribute
            '/<(' . $skippedElements . ')(?:[-\w]+)?(?:[^>]+)?>([\s\S]*?)<\/\1>/m', // skipped elements
        ];

        return $this;
    }

    private function unsetCodePlaceholders() : self
    {
        $codePlaceholders = array_reverse($this->codePlaceholders);

        foreach ($codePlaceholders as $codePlaceholder => $code)
            $this->output = str_replace($codePlaceholder, $code, $this->output);

        $this->output = preg_replace(self::FORMATTER_OFF_REMOVE_RE, '', $this->output);
        $this->codePlaceholders = [];

        return $this;
    }
}
