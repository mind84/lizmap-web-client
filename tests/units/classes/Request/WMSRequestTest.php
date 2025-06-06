<?php

use Lizmap\Project\Repository;
use Lizmap\Request\OGCResponse;
use Lizmap\Request\WMSRequest;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class WMSRequestTest extends TestCase
{
    public function testParameters(): void
    {
        $params = array(
            'request' => 'falseRequest',
            'service' => 'WMS',
        );
        $expectedParams = array(
            'request' => 'falseRequest',
            'service' => 'WMS',
            'map' => null,
            'Lizmap_User' => '',
            'Lizmap_User_Groups' => '',
        );
        $wms = new WMSRequest(new ProjectForOGCForTests(), $params, null);
        $this->assertEquals($expectedParams, $wms->parameters());
        $params = array(
            'request' => 'getmap',
            'service' => 'WMS',
        );
        $expectedParams = array(
            'request' => 'getmap',
            'service' => 'WMS',
            'map' => null,
            'Lizmap_User' => '',
            'Lizmap_User_Groups' => '',
        );
        $proj = new ProjectForOGCForTests();
        $proj->setRepo(new Repository('key', array(), '', null, new ContextForTests()));
        $wms = new WMSRequest($proj, $params, null);
        $this->assertEquals($expectedParams, $wms->parameters());
    }

    public static function getParametersWithFilterData()
    {
        $loginFilters = array(
            'layer1' => array(
                'filter' => 'test',
            ),
            'layer2' => array(
                'filter' => 'other test',
            ),
        );

        return array(
            array(array(), null, null),
            array(array('layer' => array('filter' => '')), 'layer:filter', 'layer:filter'),
            array($loginFilters, 'layer1:filter;layer:dontExists', 'layer1:filter;layer:dontExists'),
        );
    }

    /**
     * @dataProvider getParametersWithFilterData
     *
     * @param mixed $loginFilter
     * @param mixed $filter
     * @param mixed $expectedFilter
     */
    public function testParametersWithFilters($loginFilter, $filter, $expectedFilter): void
    {
        $testContext = new ContextForTests();
        $testContext->setResult(array('lizmap.tools.loginFilteredLayers.override' => false));

        $proj = new ProjectForOGCForTests($testContext);
        $proj->setRepo(new Repository('key', array(), '', null, $testContext));
        $proj->loginFilters = $loginFilter;
        $params = array(
            'request' => 'getmap',
            'service' => 'WMS',
        );
        if ($filter) {
            $params['filter'] = $filter;
        }
        $wms = new WMSRequest($proj, $params, null);
        $parameters = $wms->parameters();
        if ($expectedFilter) {
            $this->assertEquals($expectedFilter, $parameters['filter']);
        } else {
            $this->assertArrayNotHasKey('filter', $parameters);
        }
    }

    public static function getGetContextData()
    {
        $responseNoUrl = new OGCResponse(
            200,
            'text/xml',
            '<whatever><nourl/></whatever>'
        );

        $expectedResponseNoUrl = (object) array(
            'code' => 200,
            'mime' => 'text/xml',
            'data' => '<whatever><nourl/></whatever>',
        );

        $responseSimpleUrl = new OGCResponse(
            200,
            'text/xml',
            '<whatever><location xlink:href="test.google.com"/></whatever>',
        );

        $expectedResponseSimpleUrl = (object) array(
            'code' => 200,
            'mime' => 'text/xml',
            'data' => '<whatever><location xlink:href="http://localhost?repo=test&amp;project=test&amp;&amp;"/></whatever>',
        );

        $responseMultiplesUrl = new OGCResponse(
            200,
            'text/xml',
            '<xml>
            <tagtest xlink:href="http://localhost?test=test&otherTest=test"/>
            <otherTagTest>
            <just to=see if=itsworking xlink:href=""/>
            </otherTagTest>
            </xml>',
        );

        $expectedResponseMultiplesUrl = (object) array(
            'code' => 200,
            'mime' => 'text/xml',
            'data' => '<xml>
            <tagtest xlink:href="http://localhost?repo=test&amp;project=test&amp;&amp;"/>
            <otherTagTest>
            <just to=see if=itsworking xlink:href="http://localhost?repo=test&amp;project=test&amp;&amp;"/>
            </otherTagTest>
            </xml>',
        );

        return array(
            array($responseNoUrl, 'http://localhost?repo=test&project=test', $expectedResponseNoUrl),
            array($responseSimpleUrl, 'http://localhost?repo=test&project=test', $expectedResponseSimpleUrl),
            array($responseSimpleUrl, 'http://localhost?repo=test&project=test', $expectedResponseSimpleUrl),
        );
    }

    /**
     * @dataProvider getGetContextData
     *
     * @param mixed $response
     * @param mixed $url
     * @param mixed $expectedResponse
     */
    public function testGetContext($response, $url, $expectedResponse): void
    {
        $testContext = new ContextForTests();
        $testContext->setResult(array('fullUrl' => $url));

        $proj = new ProjectForOGCForTests($testContext);
        $proj->setKey('proj');
        $proj->setRepo(new Repository('key', array(), '', null, $testContext));
        $wmsMock = $this->getMockBuilder(WMSRequestForTests::class)
            ->onlyMethods(array('request'))
            ->setConstructorArgs(array($proj, array(), null))
            ->getMock()
        ;
        $wmsMock->method('request')->willReturn($response);
        $newResponse = $wmsMock->getContextForTests();
        foreach ($expectedResponse as $prop => $value) {
            $this->assertEquals($value, $newResponse->{$prop});
        }
    }

    public static function getCheckMaximumWidthHeightData()
    {
        return array(
            array(50, 25, 50, 25, false, false),
            array(50, 25, 50, 25, true, false),
            array(50, 25, 50, 55, false, false),
            array(50, 55, 50, 25, true, false),
            array(50, 55, 50, '', true, true),
            array(50, 55, 50, '', false, true),
        );
    }

    /**
     * @dataProvider getCheckMaximumWidthHeightData
     *
     * @param mixed $width
     * @param mixed $maxWidth
     * @param mixed $height
     * @param mixed $maxHeight
     * @param mixed $useServices
     * @param mixed $expectedBool
     */
    public function testCheckMaximumWidthHeight($width, $maxWidth, $height, $maxHeight, $useServices, $expectedBool): void
    {
        $params = array(
            'width' => $width,
            'height' => $height,
        );
        $proj = new ProjectForOGCForTests();
        $proj->setWMSMaxWidthHeight(
            $useServices ? '' : $maxWidth,
            $useServices ? '' : $maxHeight
        );
        $services = (object) array(
            'wmsMaxWidth' => $useServices ? $maxWidth : '',
            'wmsMaxHeight' => $useServices ? $maxHeight : '',
        );
        $wms = new WMSRequestForTests($proj, $params, $services);
        $this->assertEquals($expectedBool, $wms->checkMaximumWidthHeightForTests());
    }

    public static function getUseCacheData()
    {
        return array(
            array(array(), null, false, false, 'web'),
            array(array('width' => 50, 'height' => 50), null, 'true', false, 'web'),
            array(array('width' => 50, 'height' => 50), 'not null', 'true', true, 'web'),
            array(array('width' => 50, 'height' => 51), null, 'true', false, 'web'),
            array(array('width' => 50, 'height' => 51), 'not null', 'true', true, 'web'),
            array(array('width' => 50, 'height' => 351), 'not null', 'true', false, 'gis'),
            array(array('width' => 50, 'height' => 351), null, 'true', false, 'gis'),
        );
    }

    /**
     * @dataProvider getUseCacheData
     *
     * @param mixed $params
     * @param mixed $cacheDriver
     * @param mixed $cached
     * @param mixed $expectedUseCache
     * @param mixed $expectedWmsClient
     */
    public function testUseCache($params, $cacheDriver, $cached, $expectedUseCache, $expectedWmsClient): void
    {
        $testContext = new ContextForTests();
        $testContext->setResult(array('cacheDriver' => $cacheDriver));
        $wms = new WMSRequestForTests(new ProjectForOGCForTests($testContext), array(), null);
        $configLayer = (object) array('cached' => $cached);
        list($useCache, $wmsClient) = $wms->useCacheForTests($configLayer, $params, '');
        $this->assertEquals($expectedUseCache, $useCache);
        $this->assertEquals($expectedWmsClient, $wmsClient);
    }

    public function testRegexpMediaUrls(): void
    {
        $testContext = new ContextForTests();
        $wms = new WMSRequestForTests(new ProjectForOGCForTests($testContext), array(), null);

        // src attribute for media directory
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/foo.bar"',
            $matches,
        );
        $this->assertCount(2, $matches);
        $this->assertEquals(
            'media/foo.bar',
            $matches[1],
        );
        // href attribute for media directory
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'href="media/foo.bar"',
            $matches,
        );
        $this->assertCount(2, $matches);
        $this->assertEquals(
            'media/foo.bar',
            $matches[1],
        );

        // src attribute for parent media directory
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="../media/foo.bar"',
            $matches,
        );
        $this->assertCount(3, $matches);
        $this->assertEquals(
            '../media/foo.bar',
            $matches[1],
        );

        // href attribute for parent media directory
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'href="../media/foo.bar"',
            $matches,
        );
        $this->assertCount(3, $matches);
        $this->assertEquals(
            '../media/foo.bar',
            $matches[1],
        );

        // multiple attributes like in a maptip
        $maptipValue = preg_replace_callback(
            $wms->getRegexpMediaUrlsForTests(),
            array($wms, 'replaceMediaPathByMediaUrlForTests'),
            '<a href="media/foo.bar"><img src="media/foo.bar"></a>',
        );
        $this->assertEquals(
            '<a href="getMedia?path=media/foo.bar"><img src="getMedia?path=media/foo.bar"></a>',
            $maptipValue,
        );

        // Accepted extensions with 2 characters
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/foobar.7z"',
            $matches,
        );
        $this->assertCount(2, $matches);
        $this->assertEquals(
            'media/foobar.7z',
            $matches[1],
        );

        // does not match directory
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/"',
            $matches,
        );
        $this->assertCount(0, $matches);
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/foo/bar/"',
            $matches,
        );
        $this->assertCount(0, $matches);
        // does not match no extension
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/foo/bar"',
            $matches,
        );
        $this->assertCount(0, $matches);
        // does not match only extension
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/.bar"',
            $matches,
        );
        $this->assertCount(0, $matches);
        // does not match extension with 1 character
        preg_match(
            $wms->getRegexpMediaUrlsForTests(),
            'src="media/foobar.z"',
            $matches,
        );
        $this->assertCount(0, $matches);
    }
}
