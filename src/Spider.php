<?php
namespace Adolphgithub\Cnregion;

use Goutte\Client;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\DomCrawler\Crawler;

class Spider
{
    // 默认得抓取地址.
    private $start_url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2021/';

    public function run($save_path)
    {
        $province_list = $this->getProvinceData();

        // 开始去获取city数据.

        $data = $this->startCrawl($province_list);

        // 开始生成sql数据了.
        $this->buildSql($save_path, $data);
    }

    public function setStartUrl($url)
    {
        $this->start_url = $url;
    }

    private function getProvinceData()
    {
        $province_name_alias = [
            '北京市' => '北京',
            '天津市'  => '天津',
            '河北省'  => '河北',
            '山西省'  => '山西',
            '内蒙古自治区' => '内蒙古',
            '辽宁省'  => '辽宁',
            '吉林省'  => '吉林',
            '黑龙江省' => '黑龙江',
            '上海市'  => '上海',
            '江苏省'  => '江苏',
            '浙江省'  => '浙江',
            '安徽省'  => '安徽',
            '福建省'  => '福建',
            '江西省'  => '江西',
            '山东省'  => '山东',
            '河南省'  => '河南',
            '湖北省'  => '湖北',
            '湖南省'  => '湖南',
            '广东省'  => '广东',
            '广西壮族自治区'  => '广西',
            '海南省'  => '海南',
            '重庆市'  => '重庆',
            '四川省'  => '四川',
            '贵州省'  => '贵州',
            '云南省'  => '云南',
            '西藏自治区'  => '西藏',
            '陕西省'  => '陕西',
            '甘肃省'  => '甘肃',
            '青海省'  => '青海',
            '宁夏回族自治区'  => '宁夏',
            '新疆维吾尔自治区'  => '新疆',
            '台湾省'  => '台湾',
            '香港特别行政区'  => '香港',
            '澳门特别行政区'  => '澳门',
        ];

        $client = new Client();

        $crawler = $client->request('GET', $this->start_url);

        $province_list = [];

        $crawler->filter('.provincetr a')->each(function($node) use(&$province_list) {
            /** @var Link $link*/
            $link = $node->link();
            $link->getUri();

            $province_list[] = [
                'name' => $link->getNode()->nodeValue,
                'link' => $link->getUri(),
            ];
        });

        foreach($province_list as &$v) {
            $v['id'] = str_replace($this->start_url, '', $v['link']);
            $v['id'] = str_pad(str_replace('.html', '', $v['id']), 9, '0', STR_PAD_RIGHT);
            $v['province_id'] = $v['id'];
            $v['province'] = $v['name'];
            $v['province_alias_name'] = $province_name_alias[$v['name']];
            $v['city_id'] = 0;
            $v['city'] = '';
            $v['area_id'] = 0;
            $v['area'] = '';
            $v['region_id'] = 0;
            $v['region_name'] = '';
        }

        return $province_list;
    }

    private function startCrawl($province_data)
    {
        $client = new Client();

        $data = [];

        foreach($province_data as $province) {
            print('province: ' . $province['name'] . "  start\n");
            $crawler = $client->request('GET', $province['link']);

            unset($province['link']);

            $city_list = [];

            $data[] = $province;

            $crawler->filter('.citytr')->each(function(Crawler $c) use (&$city_list, $province) {
                $id = $c->filter('td')->getNode(0)->nodeValue;
                $city_id = substr($id, 0, 9);
                $city_name = $c->filter('td')->getNode(1)->nodeValue;

                $link = $c->filter('td a:nth-child(1)')->link();

                $city_list[] = [
                    'id' => $city_id,
                    'name' => $city_name,
                    'province_id' => $province['id'],
                    'province' => $province['name'],
                    'province_alias_name' => $province['province_alias_name'],
                    'city_id' => $city_id,
                    'city' => $city_name,
                    'area_id' => 0,
                    'area' => '',
                    'region_id' => 0,
                    'region_name' => '',
                    'link' => $link->getUri(),
                ];
            });

            foreach($city_list as $city) {
                $link = $city['link'];

                unset($city['link']);
                print('city    : ' . $city['name'] . " start\n");
                $crawler = $client->request('GET', $link);

                $data[] = $city;

                $result = $crawler->filter('.countytr')->count()
                    ? $crawler->filter('.countytr')
                    : $crawler->filter('.towntr');

                $result->each(function(Crawler $c) use (&$data, $city) {
                    $id = $c->filter('td')->getNode(0)->nodeValue;
                    $area_id = substr($id, 0, 9);
                    $area_name = $c->filter('td')->getNode(1)->nodeValue;

                    $data[] = [
                        'id' => $area_id,
                        'name' => $area_name,
                        'province_id' => $city['province_id'],
                        'province' => $city['province'],
                        'province_alias_name' => $city['province_alias_name'],
                        'city_id' => $city['id'],
                        'city' => $city['name'],
                        'area_id' => $area_id,
                        'area' => $area_name,
                        'region_id' => 0,
                        'region_name' => '',
                    ];
                });

                print($province['name'] . "  start\n");
            }
        }

        return $data;
    }

    private function buildSql($save_path, $data)
    {
        $table = "CREATE TABLE `city` (
  `id` int(11) NOT NULL DEFAULT '0' COMMENT 'ID',
  `name` varchar(20) NOT NULL DEFAULT '' COMMENT '地址名称',
  `province_id` int(11) NOT NULL DEFAULT '0' COMMENT '省id',
  `province` varchar(20) NOT NULL DEFAULT '' COMMENT '省名称',
  `province_alias_name` varchar(20) NOT NULL DEFAULT '' COMMENT '省份别名',
  `city_id` int(11) NOT NULL DEFAULT '0' COMMENT '市id',
  `city` varchar(20) NOT NULL DEFAULT '' COMMENT '市名称',
  `area_id` int(11) NOT NULL DEFAULT '0' COMMENT '区域id',
  `area` varchar(20) NOT NULL DEFAULT '0' COMMENT '区域名称',
  `region_id` tinyint(4) NOT NULL DEFAULT '0' COMMENT '区域id，0：其他 1：华北 2：东北 3：西北 4：华南 5：华中 6：西南 7：华东',
  `region_name` varchar(20) NOT NULL DEFAULT '' COMMENT '区域名称 如：华北',
  PRIMARY KEY (`id`),
  KEY `idx_province_id` (`province_id`),
  KEY `idx_city_id` (`city_id`),
  KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='省市区表';\n";

        foreach($data as $item) {
            $sql = sprintf("INSERT INTO city VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');\n",
                $item['id'], $item['name'], $item['province_id'], $item['province'], $item['province_alias_name'],
                $item['city_id'], $item['city'], $item['area_id'], $item['area'], $item['region_id'], $item['region_name']
            );

            $table .= $sql;
        }

        file_put_contents($save_path . DIRECTORY_SEPARATOR . 'region.sql', $table);

        return true;
    }
}