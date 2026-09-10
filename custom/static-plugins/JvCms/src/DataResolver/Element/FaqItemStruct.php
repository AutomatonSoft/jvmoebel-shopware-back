<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One question and answer in Store API `data.items[]`. */
final class FaqItemStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int|float $position,
        protected string $question,
        protected string $answer,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int|float
    {
        return $this->position;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_faq_item';
    }
}
