# phpems v5.0 with redis
开源免费的PHP无纸化模拟考试系统，基于 [oiuv/phpems](https://github.com/oiuv/phpems)对[PHPEMS](http://www.phpems.net) 修改的基础之上。
使用redis驱动整个系统的session和examsession，大大减少系统运行时的I/O，提高运行速度。


## 安装

    git clone git@github.com:williamyang233/phpems_redis.git
    cd phpems_redis
    composer install
    
安装完成后，修改 `/lib/config.inc.php` 配置文件并导入数据库`phpems5.sql`



## 关于系统的二次开发说明：

### PHPEMS 路由说明

    index.php?user-phone-login-index
    
> 访问 `app` 目录下 user/controller/login.phone.php 文件的index方法

### PHPEMS smarty模板标签

#### 变量
    
    {x2;$var}

> 该标签会被翻译为<?php echo 变量; ?>该变量必须为在php程序中被$this->tpl->assign过后的变量。

    {x2;v:var}

> 该标签会被翻译为<?php echo $var; ?>该变量是在php模板中产生的临时变量，不需要assign

    {x2;c:const}

> 该标签用于显示常量，注意，在以后的if,tree,loop等标签中，常量不需要c:，只在显示常量的本标签中需要c:

#### 循环遍历：tree

tree标签是一个组合标签，用于遍历一个数组。规则如下
````

{x2;tree:遍历变量，临时指针变量，循环次数变量}

{x2;endtree}

````

#### 逻辑判断：if

if标签格式：
````
{x2;if:判断语句}

......

{x2;elseif:判断语句}

......

{x2;else}

......

{x2;endif}

````

#### 字符处理

    date

> 将unix时间戳转换为具体时间，用法{x2;date:变量,'Y-m-d H:i:s'}

    substring

> 字符串截取，用法{x2;substring:变量,长度数字}

    realhtml

> 取消转义并显示带html的内容，{x2;realhtml:变量}
