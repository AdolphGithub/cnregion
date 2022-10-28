省市区三级区域爬虫
### 如何执行
```
git clone git@github.com:AdolphGithub/cnregion.git
cd cnregion
composer install 
php run.php
```
执行完成过后可在data目录下得到region.sql
### 爬取站点
[国家统计局2021年数据](http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2021/)
### 如何持续更新
```
$spider = new \Adolphgithub\Cnregion\Spider();

$spider->setStartUrl('最新爬取网址');

$spider->run();
```
### 最后
祝大家使用愉快