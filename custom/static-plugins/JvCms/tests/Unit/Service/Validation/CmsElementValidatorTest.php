<?php declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\Service\Validation;

use Jv\Cms\Service\Validation\CmsElementValidator;
use Jv\Cms\Service\Validation\FaqCmsElementValidationRule;
use Jv\Cms\Service\Validation\HomeEditorialCmsElementValidationRule;
use PHPUnit\Framework\TestCase;

final class CmsElementValidatorTest extends TestCase
{
    public function testItRoutesValidationByElementTypeAndAllowsSavingByDefault(): void
    {
        $validator = $this->validator();

        self::assertSame([], $validator->validate('unregistered-element', []));

        $errors = $validator->validate('jv-faq', [
            'items' => [
                'value' => [
                    ['question' => '', 'answer' => '<p>Answer</p>'],
                    ['question' => 'Question', 'answer' => ''],
                ],
            ],
        ]);

        self::assertSame(
            ['/config/items/value/0/question', '/config/items/value/1/answer'],
            array_column($errors, 'fieldPath'),
        );
        self::assertSame([false, false], array_column($errors, 'blockSave'));
    }

    public function testFaqRuleAcceptsCompleteItems(): void
    {
        $errors = $this->validator()->validate('jv-faq', [
            'items' => [
                'value' => [
                    ['question' => 'Question', 'answer' => '<p>Answer</p>'],
                ],
            ],
        ]);

        self::assertSame([], $errors);
    }

    public function testHomeEditorialRuleTargetsTheSectionOrItsEmptyParagraphFields(): void
    {
        $validator = $this->validator();

        $missingParagraphs = $validator->validate('jv-home-editorial', [
            'sections' => ['value' => [['paragraphs' => []]]],
        ]);
        self::assertSame('/config/sections/value/0/paragraphs', $missingParagraphs[0]->fieldPath);

        $emptyParagraphs = $validator->validate('jv-home-editorial', [
            'sections' => ['value' => [['paragraphs' => ['', '  ']]]],
        ]);
        self::assertSame(
            ['/config/sections/value/0/paragraphs/0', '/config/sections/value/0/paragraphs/1'],
            array_column($emptyParagraphs, 'fieldPath'),
        );

        $validParagraphs = $validator->validate('jv-home-editorial', [
            'sections' => ['value' => [['paragraphs' => ['', '<p>Content</p>']]]],
        ]);
        self::assertSame([], $validParagraphs);
    }

    private function validator(): CmsElementValidator
    {
        return new CmsElementValidator([
            new FaqCmsElementValidationRule(),
            new HomeEditorialCmsElementValidationRule(),
        ]);
    }
}
