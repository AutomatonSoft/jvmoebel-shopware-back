<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

enum RedirectType: string
{
    case General = 'general';
    case Product = 'product';
    case Category = 'category';
    case Pages = 'pages';
    case Image = 'image';
}
