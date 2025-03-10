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

namespace CoreShop\Bundle\CoreBundle\Pimcore\Repository;

use CoreShop\Bundle\ProductBundle\Pimcore\Repository\ProductRepository as BaseProductRepository;
use CoreShop\Component\Core\Model\CategoryInterface;
use CoreShop\Component\Core\Model\ProductInterface;
use CoreShop\Component\Core\Repository\ProductRepositoryInterface;
use CoreShop\Component\Core\Repository\ProductVariantRepositoryInterface;
use CoreShop\Component\Store\Model\StoreInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Listing;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductRepository extends BaseProductRepository implements ProductRepositoryInterface, ProductVariantRepositoryInterface
{
    public function findLatestByStore(StoreInterface $store, int $count = 8): array
    {
        $conditions = [
            ['condition' => 'active = ?', 'variable' => 1],
            ['condition' => 'stores LIKE ?', 'variable' => '%,' . $store->getId() . ',%'],
        ];

        /** @psalm-suppress InvalidScalarArgument */
        return $this->findBy($conditions, ['o_creationDate' => 'DESC'], $count);
    }

    public function findAllVariants(ProductInterface $product, bool $recursive = true): array
    {
        $list = $this->getList();
        $list->setObjectTypes([AbstractObject::OBJECT_TYPE_VARIANT]);

        if ($recursive) {
            $list->setCondition('o_path LIKE ?', [$product->getRealFullPath() . '/%']);
        } else {
            $list->setCondition('o_parentId = ?', [$product->getId()]);
        }

        return $list->getObjects();
    }

    public function findRecursiveVariantIdsForProductAndStore(ProductInterface $product, StoreInterface $store): array
    {
        $list = $this->getList();
        $dao = $list->getDao();

        /** @psalm-suppress InternalMethod */
        $query = $this->connection->createQueryBuilder()
            ->select()
            ->from($dao->getTableName())
            ->select('oo_id')
            ->where('o_path LIKE :path')
            ->andWhere('stores LIKE :stores')
            ->andWhere('o_type = :variant')
            ->setParameter('path', $product->getRealFullPath() . '/%')
            ->setParameter('stores', '%,' . $store->getId() . ',%')
            ->setParameter('variant', 'variant')
        ;

        $variantIds = [];

        $result = $this->connection->fetchAllAssociative($query->getSQL(), $query->getParameters());

        foreach ($result as $column) {
            $variantIds[] = $column['oo_id'];
        }

        return $variantIds;
    }

    public function getProducts(array $options = []): array
    {
        $list = $this->getProductsListing($options);

        /**
         * @var ProductInterface[] $products
         */
        $products = $list->load();

        return $products;
    }

    public function getProductsListing(array $options = []): Listing
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'only_active' => true,
            'categories' => [],
            'store' => null,
            'order_key' => 'name',
            'order' => 'ASC',
            'order_key_quote' => true,
            'object_types' => null,
            'return_type' => 'objects',
        ]);

        $resolver->setRequired(['store']);
        $resolver->setAllowedTypes('only_active', 'bool');
        $resolver->setAllowedTypes('categories', 'array');
        $resolver->setAllowedTypes('store', ['null', StoreInterface::class]);
        $resolver->setAllowedTypes('order_key', 'string');
        $resolver->setAllowedTypes('order_key_quote', 'bool');
        $resolver->setAllowedTypes('order', 'string');
        $resolver->setAllowedTypes('object_types', ['null', 'array']);
        $resolver->setAllowedValues('object_types', function (?array $value) {
            $valid = [
                null,
                AbstractObject::OBJECT_TYPE_FOLDER,
                AbstractObject::OBJECT_TYPE_OBJECT,
                AbstractObject::OBJECT_TYPE_VARIANT,
            ];

            return $value === null || !array_diff($value, $valid);
        });

        $listOptions = $resolver->resolve($options);

        $list = $this->getList();

        if ($listOptions['object_types'] !== null) {
            $list->setObjectTypes($listOptions['object_types']);
        }

        if ($listOptions['only_active'] === true) {
            $list->addConditionParam('active = ?', 1);
        }

        $classId = $this->getClassId();
        if (count($listOptions['categories']) > 0) {
            $categoryIds = [];
            foreach ($listOptions['categories'] as $category) {
                if ($category instanceof CategoryInterface) {
                    $categoryIds[] = $category->getId();
                }
            }
            if (count($categoryIds) > 0) {
                $list->addConditionParam('(o_id IN (SELECT DISTINCT src_id FROM object_relations_' . $classId . ' WHERE fieldname = "categories" AND dest_id IN (' . implode(',', $categoryIds) . ')))');
            }
        }

        if ($listOptions['store'] instanceof StoreInterface) {
            $list->addConditionParam('stores LIKE ?', '%,' . $listOptions['store']->getId() . ',%');
        }

        $list->setOrderKey($listOptions['order_key'], $listOptions['order_key_quote']);
        $list->setOrder($listOptions['order']);

        return $list;
    }
}
