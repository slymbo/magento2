<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Elasticsearch\Test\Unit\Model\Adapter;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Elasticsearch\SearchAdapter\FieldMapperInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class FieldMapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Elasticsearch\Model\Adapter\FieldMapper
     */
    protected $mapper;

    /**
     * @var \Magento\Eav\Model\Config|MockObject
     */
    protected $eavConfig;

    /**
     * @var \Magento\Framework\Registry|MockObject
     */
    protected $coreRegistry;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $storeManager;

    /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute|\PHPUnit_Framework_MockObject_MockObject */
    protected $eavAttributeResource;

    protected function setUp()
    {
        $this->eavConfig = $this->getMockBuilder('\Magento\Eav\Model\Config')
            ->disableOriginalConstructor()
            ->setMethods(['getEntityType', 'getAttribute', 'getEntityAttributeCodes'])
            ->getMock();

        $this->fieldType = $this->getMockBuilder('\Magento\Elasticsearch\Model\Adapter\FieldType')
            ->disableOriginalConstructor()
            ->setMethods(['getFieldType'])
            ->getMock();

        $this->storeManager = $this->getMock('Magento\Store\Model\StoreManagerInterface');

        $objectManager = new ObjectManagerHelper($this);

        $this->eavAttributeResource = $this->getMock(
            '\Magento\Catalog\Model\ResourceModel\Eav\Attribute',
            [
                '__wakeup',
                'getBackendType',
                'getFrontendInput'
            ],
            [],
            '',
            false
        );

        $this->mapper = $objectManager->getObject(
            '\Magento\Elasticsearch\Model\Adapter\FieldMapper',
            [
                'eavConfig' => $this->eavConfig,
                'coreRegistry' => $this->coreRegistry,
                'storeManager' => $this->storeManager,
                'fieldType' => $this->fieldType
            ]
        );
    }

    /**
     * @dataProvider attributeCodeProvider
     * @param $attributeCode
     * @param $fieldName
     * @param $fieldType
     * @param array $context
     *
     * @return string
     */
    public function testGetFieldName($attributeCode, $fieldName, $fieldType, $context = [])
    {
        $attributeMock = $this->getMockBuilder('Magento\Catalog\Model\ResourceModel\Eav\Attribute')
            ->setMethods(['getBackendType', 'getFrontendInput', 'getAttribute'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->eavConfig->expects($this->any())->method('getAttribute')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode)
            ->willReturn($attributeMock);

        $attributeMock->expects($this->any())->method('getFrontendInput')
            ->will($this->returnValue('select'));

        $this->fieldType->expects($this->any())->method('getFieldType')
            ->with($attributeMock)
            ->willReturn($fieldType);

        $this->assertEquals(
            $fieldName,
            $this->mapper->getFieldName($attributeCode, $context)
        );
    }

    /**
     * @return string
     */
    public function testGetFieldNameWithoutAttribute()
    {
        $this->eavConfig->expects($this->any())->method('getAttribute')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE, 'attr1')
            ->willReturn('');

        $this->assertEquals(
            'attr1',
            $this->mapper->getFieldName('attr1', [])
        );
    }

    /**
     * @dataProvider attributeProvider
     * @param $attributeCode
     *
     * @return array
     */
    public function testGetAllAttributesTypes($attributeCode)
    {
        $attributeMock = $this->getMockBuilder('Magento\Catalog\Model\ResourceModel\Eav\Attribute')
            ->setMethods(['getBackendType', 'getFrontendInput'])
            ->disableOriginalConstructor()
            ->getMock();

        $store = $this->getMockBuilder('\Magento\Store\Model\Store')
            ->setMethods(['getId', '__wakeup'])->disableOriginalConstructor()->getMock();
        $store->expects($this->any())->method('getId')->will($this->returnValue(1));
        $this->storeManager->expects($this->any())->method('getStore')->will($this->returnValue($store));
        $this->storeManager->expects($this->any())
            ->method('getStores')
            ->will($this->returnValue([$store]));

        $this->eavConfig->expects($this->any())->method('getEntityAttributeCodes')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->willReturn([$attributeCode]);

        $this->eavConfig->expects($this->any())->method('getAttribute')
            ->with(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode)
            ->willReturn($attributeMock);

        $attributeMock->expects($this->any())->method('getFrontendInput')
            ->will($this->returnValue('select'));

        $this->eavAttributeResource->expects($this->any())
            ->method('getIsFilterable')
            ->willReturn(true);
        $this->eavAttributeResource->expects($this->any())
            ->method('getIsVisibleInAdvancedSearch')
            ->willReturn(true);
        $this->eavAttributeResource->expects($this->any())
            ->method('getIsFilterableInSearch')
            ->willReturn(false);
        $this->eavAttributeResource->expects($this->any())
            ->method('getIsGlobal')
            ->willReturn(false);
        $this->eavAttributeResource->expects($this->any())
            ->method('getIsGlobal')
            ->willReturn(true);

        $this->assertInternalType(
            'array',
            $this->mapper->getAllAttributesTypes()
        );
    }

    /**
     * @return array
     */
    public static function attributeCodeProvider()
    {
        return [
            ['id', 'id', 'string'],
            ['status', 'status', 'string'],
            ['price', 'price_value', 'string', ['type'=>'default']],
            ['color', 'color_value', 'select', ['type'=>'default']],
            ['description', 'sort_description', 'string', ['type'=>'some']],
            ['*', '_all', 'string', ['type'=>'text']],
        ];
    }

    /**
     * @return array
     */
    public static function attributeProvider()
    {
        return [
            ['category_ids'],
            ['attr_code'],
        ];
    }
}
