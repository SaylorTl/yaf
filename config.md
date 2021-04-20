### 封装层接口

**git地址**:
http://git.sqycn.cn/code/middleware.git
<br/>
<br/>

***配置文件application.ini***
> 路径 src/service/config/
```
[product]

application.directory = APP_PATH
application.bootstrap = APP_PATH "/Bootstrap.php"
application.library = SERVICE_PATH "/library"
application.dispatcher.throwException = false
application.dispatcher.catchException = false
application.modules = "Index,Manage,App"


config.env = prod
```
<br/>
<br/>

***

***配置文件ms.ini***
> 路径 src/service/config/ini/prod/ms.ini
```
; auth2.0
auth2.url = "http://127.0.0.1:7171"

; 用户微服务
user.url = "http://127.0.0.1:5007"

; 资源管理微服务
resource.url = "http://127.0.0.1:5005"

; 文件上传微服务
fileupload.url = "http://127.0.0.1:5003"

; 项目管理微服务
pm.url = "http://127.0.0.1:5002"

; 栏目微服务
tag.url = ""

; 公司微服务
company.url = "http://127.0.0.1:5004"

; 地址微服务
addr.url = "http://127.0.0.1:5001"

; 合同微服务
agreement.url = "http://127.0.0.1:5006"

; 广告微服务
adv.url = "http://127.0.0.1:5009"

; 权限微服务
access.url = "http://127.0.0.1:5011"

; 车辆微服务
car.url = "http://127.0.0.1:5010"

;路由微服务
route.url = "http://xing.aparcar.cn:5012"
```
<br/>
<br/>

***

***配置文件redis.ini***
> 路径 src/service/config/ini/prod/redis.ini
```
redis.default.host = '10.0.0.18'
redis.default.port = '8379'
redis.default.auth = 'CdJy8rTO'
```