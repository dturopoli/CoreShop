<?php

declare(strict_types=1);

/*
 * CoreShop
 *
 * This source file is available under two different licenses:
 *  - GNU General Public License version 3 (GPLv3)
 *  - CoreShop Commercial License (CCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) CoreShop GmbH (https://www.coreshop.org)
 * @license    https://www.coreshop.org/license     GPLv3 and CCL
 *
 */

namespace CoreShop\Component\Pimcore\Slug;

use Pimcore\Http\Request\Resolver\SiteResolver;
use Pimcore\Model\DataObject\ClassDefinition\LinkGeneratorInterface;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Component\HttpFoundation\RequestStack;

class SluggableLinkGenerator implements LinkGeneratorInterface
{
    public function __construct(
        private SiteResolver $siteResolver,
        private RequestStack $requestStack,
    ) {
    }

    public function generate(Concrete $object, array $params = []): string
    {
        if (!$object instanceof SluggableInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Object with Path "%s" must implement %s',
                $object->getFullPath(),
                SluggableInterface::class,
            ));
        }

        $slugs = $object->getSlug($params['_locale'] ?? null);
        $slug = null;
        $site = $params['site'] ?? (
            $this->requestStack->getMainRequest() ?
                $this->siteResolver->getSite($this->requestStack->getMainRequest()) :
                null
        );

        foreach ($slugs as $possibleSlug) {
            if ($possibleSlug->getSiteId() === ($site ? $site->getId() : 0)) {
                $slug = $possibleSlug;

                break;
            }
        }

        if (null === $slug) {
            throw new \InvalidArgumentException(sprintf('No Valid Slug found for object "%s"', $object->getFullPath()));
        }

        return $slug->getSlug();
    }
}
