<?php
namespace Home\Controller;
use Think\Controller;
class ApiController extends Controller {
    public function _initialize() {
        //引入WxPayPubHelper
        vendor('WxPayPubHelper.WxPayPubHelper');
        //引入Alipay
        vendor('Alipay.Corefunction');
        vendor('Alipay.Md5function');
        vendor('Alipay.Notify');
        vendor('Alipay.Submit');
        header("Content-type:text/html;charset=utf-8");
        $date=date('Y-m-d H:i:s',time());
        $content="\n".$date."\nREMOTE_ADDR=======>".$_SERVER['REMOTE_ADDR']."\nHTTP_USER_AGENT=======>".$_SERVER['HTTP_USER_AGENT'].
            "\nHTTP_HOST=====>".$_SERVER['HTTP_HOST']."\nREQUEST_URI=======>".$_SERVER['REQUEST_URI'].
            "\nREQUEST_METHOD======>".$_SERVER['REQUEST_METHOD']."\nPATH_INFO========>".$_SERVER['PATH_INFO'].
            "\nHTTP_COOKIE=======>".$_SERVER['HTTP_COOKIE']."\nRequestUrl=======>".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n";
        $file=date('Y-m-d').'Apilog.log';
        file_put_contents($file,$content,FILE_APPEND);
    }

    /*
     * 发送验证码
     */
    public function doSendVerifyCode()
    {
        $account = trim($_REQUEST['account']);
        $sendType = trim($_REQUEST['sendType']);
        if (empty($account)) {
            $rtn['code'] = 201;
            $rtn['message'] = '账号不能为空';
            $this->ajaxReturn($rtn);
        }
        if ($sendType != 1 && $sendType != 2) {
            $rtn['code'] = 201;
            $rtn['message'] = '验证码类型参数错误！';
            $this->ajaxReturn($rtn);
        }

        $code = rand('100000', '999999');
        if ($sendType == 1) {
            $title = '注册账号';
            $content = "您的验证码是：" . $code . "； 用于注册犹太餐饮中心，有效期为10分钟，请尽快提交您的验证码。为保证信息安全，切勿将验证码告知他人。若非本人操作，请忽略此信息。";
        } elseif ($sendType == 2) {
            $title = '找回密码';
            $content = "您的验证码是：" . $code . "； 用于注册犹太餐饮中心，有效期为10分钟，请尽快提交您的验证码。为保证信息安全，切勿将验证码告知他人。若非本人操作，请警惕账号安全。";
        }
        //验证邮箱
        if (preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$account )) {
            //发送邮件验证码
            //SendMail('44311238@qq.com','测试邮件','test email by hpc!')
            $bool1 = SendMail($account, $title, $content);
            $bool = $bool1['status'];
        } elseif (preg_match("/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$/", $account)) {  //验证手机号码
            //发送手机验证码
            $bool = self::sendSms1($account, $content);
        } else {
            $rtn['code'] = 201;
            $rtn['message'] = '账号类型错误，请检查是否为邮箱或手机号！';
            $this->ajaxReturn($rtn);
        }

        if ($bool)
        {
            $yxtime = time()+10 * 60;
            $data['account'] = $account;
            $data['type'] = $sendType;
            $data['code'] = $code;
            $data['effective_time'] = $yxtime;    //验证码有效期截止时间
            $data['create_time'] = time();
            $id =  D('code')->add($data);
            if ($id) {
                $rtn['code'] = 200;
                $rtn['message'] = '发送成功！';
            }
        } else {
            $rtn['code'] = 201;
            $rtn['message'] = $bool1['error'] . ' 发送失败!';
        }
        $this->ajaxReturn($rtn);
    }

    //发送短信
    private function sendSms1($strmobile, $content) {
        $url = 'http://222.73.117.158/msg/HttpBatchSendSM';
        $username = "jiekou-clcs-03"; //用户名
        $password = "BHM465bjhj"; //密码
        $post_data = "account={$username}&pswd={$password}&mobile={$strmobile}&msg=".rawurlencode($content);
        //密码可以使用明文密码或使用32位MD5加密
        $gets = $this->Post($post_data, $url);
        return $gets;
    }

    // 抓取短信验证码返回信息
    public function Post($curlPost, $url) {
        $curl = curl_init();   //创建curl
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        $return_str = curl_exec($curl);    //执行
        curl_close($curl);   //释放
        return $return_str;
    }

    //注册
    public function regist() {
        $rtn['code'] = 201;
        $surname = trim($_REQUEST['surname']);
        $firstname = trim($_REQUEST['firstname']);
        $verifyCode = trim($_REQUEST['verifyCode']);
        $account = trim($_REQUEST['account']);
        $password = trim($_REQUEST['password']);
        $memberType = trim($_REQUEST['memberType']);

        //参数验证
        if (!($surname && $firstname && $verifyCode && $account && $password && $memberType)) {
            $rtn['message'] = '请填写完整的参数！';
            $this->ajaxReturn($rtn);
        }
        if (!($memberType == 1 || $memberType == 2)) {
            $rtn['message'] = '学生参数错误！';
            $this->ajaxReturn($rtn);
        }
        //验证码判断
        $mapCode['account'] = $account;
        $mapCode['code'] = $verifyCode;
        $codeInfo = D('code')->where($mapCode)->order('create_time desc')->find();
        if ($codeInfo) {
            if (time() > $codeInfo['effective_time']) {
                $rtn['message'] = '验证码已过期！';
                $this->ajaxReturn($rtn);
            }
        }else {
            $rtn['message'] = '验证码不正确！';
            $this->ajaxReturn($rtn);
        }

        //验证账号类型
        if (preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$account )) {
            $data['email'] = $account;
        } elseif (preg_match("/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$/", $account)) {
            $data['mobile'] = $account;
        }
        $data['surname'] = $surname;
        $data['firstname'] = $firstname;
        $data['password'] = $password;
        if ($memberType == 1) {
            $data['member_type'] = 2;   //学生
        } else {
            $data['member_type'] = 1;   //普通用户
        }
        $data['create_time'] = time();
        $addId=D('member')->add($data);
        if ($addId) {
            $rtn['code'] = 200;
            $rtn['message'] = '注册成功！';
            $memberInfo = D('member')->where("id=$addId")->find();
            if($memberInfo){
                $mem_arr['memberId'] = $memberInfo['id'];
                $mem_arr['account'] = $memberInfo['mobile']?$memberInfo['mobile']:$memberInfo['email'];
                $mem_arr['memberType'] = $memberInfo['member_type'];
                $mem_arr['moneyType'] = $memberInfo['money_type'];
                $mem_arr['consume'] = $memberInfo['consume'];
                $rtn['data']=$mem_arr;
            }
            $this->ajaxReturn($rtn);
        } else {
            $rtn['message'] = '注册失败！';
            $this->ajaxReturn($rtn);
        }
    }

    //登录
    public function doLogin() {
        $rtn['code'] = 201;
        $account = trim($_REQUEST['account']);
        $password = trim($_REQUEST['password']);
        if (!($account && $password)) {
            $rtn['message'] = '账号或密码不能为空！';
            $this->ajaxReturn($rtn);
        }
        if (preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$account )) {
            $data['email'] = $account;
        } elseif (preg_match("/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$/", $account)) {
            $data['mobile'] = $account;
        } else {
            $rtn['message'] = '账号类型错误，请检查是否为邮箱或手机号！';
            $this->ajaxReturn($rtn);
        }
        //登录验证
        $memberInfo = D('member')->where($data)->find();
        if ($memberInfo) {
            if ($memberInfo['password'] == $password) {
                $mem_arr['account'] = $account;
                $mem_arr['memberType'] = $memberInfo['member_type'];
                $mem_arr['memberId'] = $memberInfo['id'];
                $mem_arr['moneyType'] = $memberInfo['money_type'];
                $mem_arr['consume'] = $memberInfo['consume'];
                $rtn['code'] = 200;
                $rtn['message'] = '登录成功！';
                $rtn['data'] = $mem_arr;
                $this->ajaxReturn($rtn);
            } else {
                $rtn['message'] = '密码不正确！';
                $this->ajaxReturn($rtn);
            }
        } else {
            $rtn['message'] = '账号不存在！';
            $this->ajaxReturn($rtn);
        }
    }

    //找回密码
    public function resetPassword () {
        $rtn['code'] = 201;
        $verifyCode = trim($_REQUEST['verifyCode']);
        $account = trim($_REQUEST['account']);
        $password = trim($_REQUEST['password']);
        if (!($verifyCode && $account && $password)) {
            $rtn['message'] = '请填写完整重试！';
            $this->ajaxReturn($rtn);
        }
        //验证码判断
        $mapCode['account'] = $account;
        $mapCode['code'] = $verifyCode;
        $mapCode['type'] = 2;
        $codeInfo = D('code')->where($mapCode)->order('create_time desc')->find();
        if ($codeInfo) {
            if (time() > $codeInfo['effective_time']) {
                $rtn['message'] = '验证码已过期！';
                $this->ajaxReturn($rtn);
            }
        }else {
            $rtn['message'] = '验证码不正确！';
            $this->ajaxReturn($rtn);
        }

        //验证账号类型
        if (preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$account )) {
            $map['email'] = $account;
        } elseif (preg_match("/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$/", $account)) {
            $map['mobile'] = $account;
        } else {
            $rtn['message'] = '账号类型错误，请检查是否为邮箱或手机号！';
            $this->ajaxReturn($rtn);
        }
        $data['password'] = $password;
        $res = D('member')->where($map)->save($data);
        if ($res !== false) {
            $rtn['code'] = 200;
            $rtn['message'] = '密码修改成功！';
        } else {
            $rtn['message'] = '密码修改失败！';
        }
        $this->ajaxReturn($rtn);
    }

    //获取犹太中心首页信息
    public function getJewishIndex () {
        $rtn['code'] = 201;
        $longitude = trim($_REQUEST['longitude']); //经度
        $latitude = trim($_REQUEST['latitude']);  //纬度
        $memberId = trim($_REQUEST['memberId']);
        $jewishId = trim($_REQUEST['jewishId']);
        $jewishCase = D('jewish');

        if ($jewishId) {
            $mapJ['id'] = $jewishId;
            $jewishInfo = $jewishCase->where($mapJ)->find();
        }else {
            if (!($longitude && $latitude)) { //定位失败
                if ($memberId) {   //查询用户上次登录犹太中心
                    $mapMember['id'] = $memberId;
                    $memberInfo = D('member')->where($mapMember)->find();
                    if ($memberInfo['jewish_id']) {  //存在
                        $map['id'] = $memberInfo['jewish_id'];
                        $jewishInfo = $jewishCase->where($map)->find();
                    } else {  //不存在 返回上海犹太中心信息
                        $data['city'] = '上海市';
                        $jewishInfo = $jewishCase->where($data)->find();
                    }
                } else {
                    $data['city'] = '上海市';
                    $jewishInfo = $jewishCase->where($data)->find();
                }
            } else {  //定位成功
                $res = self::getCity($longitude, $latitude);
                $city = $res['result']['addressComponent']['city'];
                //返回当前城市犹太中心
                $data['city'] = $city;
                $jewishInfo = $jewishCase->where($data)->find();
                if ($jewishInfo) {  //当前城市存在犹太中心
                } else { //当前城市不存在犹太中心
                    if ($memberId) {   //查询用户上次登录犹太中心
                        $mapMember['id'] = $memberId;
                        $memberInfo = D('member')->where($mapMember)->find();
                        if ($memberInfo['jewish_id']) {  //存在
                            $map['id'] = $memberInfo['jewish_id'];
                            $jewishInfo = $jewishCase->where($map)->find();
                        } else {  //不存在 返回上海犹太中心信息
                            $data['city'] = '上海市';
                            $jewishInfo = $jewishCase->where($data)->find();
                        }
                    } else {
                        $data['city'] = '上海市';
                        $jewishInfo = $jewishCase->where($data)->find();
                    }
                }
            }
        }

        $jewish_arr['jewishId'] = $jewishInfo['id'];
        $jewish_arr['centerName'] = $jewishInfo['center_name'];
        $jewish_arr['centerMobile'] = $jewishInfo['center_mobile'];
        $jewish_arr['centerAddress'] = $jewishInfo['center_address'];
        $jewish_arr['imageUrl'] = $jewishInfo['image_url'];
        $jewish_arr['imageUrl'] = $jewishInfo['image_url'];
        //当前星期
        $weekarray=array("日","一","二","三","四","五","六");
        $week = "星期".$weekarray[date("w")];
        $jewish_arr['week'] = $week;
        $hours = date('H;i:s',time());
        if (date("w") == 1 || date("w") == 2 || date("w") == 3 || date("w") == 4) {
            if (date('H:i:s',$jewishInfo['begin_time'])<=$hours && date('H:i:s',$jewishInfo['end_time'])>=$hours) {
                $jewish_arr['status'] = 1;
            } else {
                $jewish_arr['status'] = 2;
            }
            $jewish_arr['beginTime'] = date('H:i:s',$jewishInfo['begin_time']);
            $jewish_arr['endTime'] = date('H:i:s',$jewishInfo['end_time']);
        } elseif (date("w") == 0) {
            if (date('H:i:s',$jewishInfo['sunday_begin_time'])<=$hours && date('H:i:s',$jewishInfo['sunday_end_time'])>=$hours) {
                $jewish_arr['status'] = 1;
            } else {
                $jewish_arr['status'] = 2;
            }
            $jewish_arr['beginTime'] = date('H:i:s',$jewishInfo['sunday_begin_time']);
            $jewish_arr['endTime'] = date('H:i:s',$jewishInfo['sunday_end_time']);
        }else {
            $jewish_arr['status'] = 2;
            $jewish_arr['beginTime'] = '周五周六不营业';
            $jewish_arr['endTime'] = '周五周六不营业';
        }
        //公告信息
        $mapamo['jewish_id'] = $jewishInfo['id'];
        $noticeInfo = D('notice')->where($mapamo)->order('id desc')->find();
        $jewish_arr['announcement'] = $noticeInfo['content'];
        //节假日信息
        $date = strtotime(date('Y-m-d',time()));
        $mapholiday['date_time'] = $date;
        $mapexchange['jewish_id'] = $mapholiday['jewish_id'] = $jewishInfo['id'];
        $holidayInfo  = D('festival')->where($mapholiday)->find();
        if ($holidayInfo) {
            $jewish_arr['festival'] = '今天为'.$holidayInfo['festival'] . ',本店不营业。';
        } else {
            $jewish_arr['festival'] = '';
        }
        //汇率、手续费
        $exchangeInfo = D('exchange')->where($mapexchange)->find();
        if ($exchangeInfo) {
            $jewish_arr['exchangeRate'] = empty($exchangeInfo['exchange_rate'])?  6.5 : $exchangeInfo['exchange_rate'];
            $jewish_arr['poundage'] = empty($exchangeInfo['poundage'])?  0 : $exchangeInfo['poundage'];
        } else {
            $jewish_arr['exchangeRate'] = 6.5;
            $jewish_arr['poundage'] = 0;
        }
        $jewish_arr['packPrice'] =  $jewishInfo['pack_price'];
        $jewish_arr['logoUrl'] =  $jewishInfo['logo_url'];
        $jewish_arr['logoUrlEat'] =  $jewishInfo['logo_url2'];
        $mapdiscuss['obj_tye'] = 1;
        $mapdiscuss['status'] = 1;
        $mapdiscuss['obj_id'] = $jewishInfo['id'];
        $commentList = D('comment')->where($mapdiscuss)->order('create_time desc')->limit(10)->select();
        $replyCase = D('comment_reply');
        $memberCase = D('member');
        foreach ($commentList as $k=>$v) {
            $mapM['id'] = $v['member_id'];
            $memberInfo2 = $memberCase->where($mapM)->find();
            $email = substr($memberInfo2['email'],0,3) . '******' .strstr($memberInfo2['email'],'@');
            $commentList[$k]['account'] = empty($memberInfo2['mobile']) ?  $email : (substr_replace($memberInfo2['mobile'],'****',3,4));
            $mapreply['comment_id'] = $v['id'];
            $replyContent = $replyCase->where($mapreply)->getField('reply_content');
            $commentList[$k]['replyContent'] = $replyContent;
            $commentList[$k]['commentContent'] = $v['comment_content'];
            $commentList[$k]['starLevel'] = $v['star_level'];
            $commentList[$k]['createTime'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $jewish_arr['commentList'] = empty($commentList)? array(): $commentList;
        $rtn['code'] = 200;
        $rtn['message'] = '首页信息获取成功';
        $rtn['data'] = $jewish_arr;
        $this->ajaxReturn($rtn);
    }

    //根据经纬度获取城市名称
    private function getCity($longitude,$latitude){
        $url = "http://api.map.baidu.com/geocoder/v2/?ak=Y8GBVhRuvX0C97BfnuGaKx6wL8PY46iI&callback=renderReverse&location=" . $latitude  . "," . $longitude ."&output=json&pois=1";
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
        $dxycontent = curl_exec($ch);
        $domain = strstr($dxycontent, '{');
        $domain = substr($domain,0,strlen($domain)-1);
        return json_decode($domain,true);
    }

    //@6 获取菜品分类
    public function getFoodTypeList()
    {
        $rtnData['code']='201';
        $rtnData['message']='获取菜品列表失败';
        $jewishId = trim($_REQUEST['jewishId']);
        $jewishWhere['jewish_id']=$jewishId;
        $foodsType=M('food_type')->where($jewishWhere)->field(array('id'=>'typeId','type_name'=>typeName))->select();
        if($foodsType){
            $rtnData['code']='200';
            $rtnData['message']='获取菜品列表成功';
            $rtnData['data']['list']=$foodsType;
        }
        $this->ajaxReturn($rtnData);
//        var_dump($this->ajaxReturn($rtnData));
    }

    //@7 获取菜品列表
    public function getFoodList()
    {
        $rtnData['code']='201';
        $rtnData['message']='获取菜品列表失败';
        $jewishId=I('jewishId','');
        $listType=I('listType','');
        $foodType=I('foodType','');
        if(empty($jewishId)||empty($listType)){
            $rtnData['message']='请求参数不全！';
            $this->ajaxReturn($rtnData);
        }
        $foodWhere['f.jewish_id']=$jewishId;
        $foodWhere['f.out_status']=$listType;
        if(!empty($foodType)){
            $foodWhere['f.is_main']=$foodType;
        }
        $rtnDataField=array(
            'j.pack_price'=>'packPrice',
            'f.type_id'=>'typeId',
            't.type_name'=>'typeName',
            'f.id'=>'foodId',
            'f.price_rmb'=>'priceRmb',
            'f.food_name'=>'foodName',
            'f.introduce'=>'introduce',
            'f.free_quantity'=>'freeQuantity',
            'f.img_url'=>'imageUrl',
            'f.is_main'=>'isMain'
        );
        if($foodType!=2){
            $food=M('food')->alias('f')->join('left join v_jewish j ON f.jewish_id=j.id')
                ->join('left join v_food_type t ON f.type_id=t.id')->field($rtnDataField)
                ->where($foodWhere)->select();
        }else{
            unset($rtnDataField['t.type_name']);
            $food=M('food')->alias('f')->join('left join v_jewish j ON f.jewish_id=j.id')
                ->field($rtnDataField)
                ->where($foodWhere)->select();
        }
        if($food){
            $rtnData['code']='200';
            $rtnData['message']='获取菜品列表成功';
            $typeList=array();
            foreach($food as $key=>$value){
                $typeId=$value['typeId'];
                $typeList[$typeId]=$typeId;
            }
            foreach($food as $key=>$value){
                foreach ($typeList as $k=>$val){
                    if($value['typeId']==$val){
                        if($value['typeId']=='0') {
                            $rtnData['data'][$val]['typeId']=0;
                            $rtnData['data'][$val]['typeName']='配菜';
                            $value['typeName']='配菜';
                            $rtnData['data'][$val]['list'][] = $value;
                        }else{
                            $rtnData['data'][$val]['typeId'] = $val;
                            $rtnData['data'][$val]['typeName'] = $value['typeName'];
                            $rtnData['data'][$val]['list'][] = $value;
                        }
                    }
                }
            }
        }else{
            $rtnData['code']='204';
            $rtnData['message']='此犹太中心没有此类菜品';
        }
        $rtnData['data']=array_values($rtnData['data']);
        $this->ajaxReturn($rtnData);
    }

    // @8 获取配菜列表
    public function getFreeFoodList()
    {
        $rtnData['code']='201';
        $rtnData['message']='获取配菜列表失败';
        $jewishId=trim($_REQUEST['jewishId']);
        $listType=trim($_REQUEST['listType']);
        if($jewishId&&$listType){
            $freeWhere['jewish_id']=$jewishId;
            $freeWhere['out_status']=$listType;
            $freeWhere['is_main']=2;
            $freeFoods=M('food')->where($freeWhere)->field('id,price_rmb,food_name,introduce,img_url')->select();
            $peiPrice=M('jewish')->where("id=$jewishId")->getField('pei_price');
            if($freeFoods){
                $rtnData['code']='200';
                $rtnData['message']='获取配菜列表成功';
                $rtnData['data']['list']=array();
                foreach ($freeFoods as $value){
                    $value['price_rmb']=$peiPrice;
                    $rtnData['data']['list'][]=getApiField($value,'foodId');
                }
            }
        }
        $this->ajaxReturn($rtnData);
    }

    // @9 提交订单
    public function doAddOrder()
    {
        /*$memberId=trim($_REQUEST['memberId']);
        $jewishId=trim($_REQUEST['jewishId']);
        $surname=trim($_REQUEST['surname']);
        $firstName=trim($_REQUEST['firstname']);
        $message=trim($_REQUEST['message']);
        $orderType=trim($_REQUEST['orderType']);
        $distribution=trim($_REQUEST['distribution']);
        $pickDate=trim($_REQUEST['pickDate']);
        $pickTime=trim($_REQUEST['pickTime']);
        $mobile=trim($_REQUEST['mobile']);
        $siteInfo=trim($_REQUEST['siteInfo']);
        $longitude=trim($_REQUEST['longitude']);
        $latitude=trim($_REQUEST['latitude']);
        $taxiFare=trim($_REQUEST['taxiFare']);
        $payment=trim($_REQUEST['payment']);
        $list=trim($_REQUEST['list']);
        $objType=$list['objType'];
        $objId=$list['objId'];
        $amount=$list['amount'];
        $goodsPrice=$list['price'];
        $freeQuantity=$list['freeQuantity'];
        $breadId=$list['list']['breadId'];
        $breadAmount=$list['list']['amount'];
        $rtnData['code']=201;
        $rtnData['message']='提交订单失败';*/
        $orderTime=time();
        // 示例，4个主菜，每个主菜有3个配菜
        $goodListExa=array(
            'meberId'=>3,
            'jewishId'=>'5',
            'pickDate'=>date('Y-m-d'),
            'pickDate'=>date('H:i:s'),
            'orderType'=>2,
            'message'=>'不要放辣椒',
            'distribution'=>2,
            'mobile'=>1368238212,
            'siteInfo'=>'上海市长宁区中山公园128号',
            'longitude'=>'130.25',
            'Latitude'=>'37.5',
            'taxiFare'=>'5.00',
            'Payment'=>2,
            'zList'=>array(
                'z1'=>array(
                    'objType'=>1,
                    'goodId'=>'3',
                    'price'=>20,
                    'amount'=>1,
                    'freeQuantity'=>3, // 免费配菜数量
                    'p1'=>array(   // 配菜列表
                        'breadId'=>'2',
                        'amount'=>1
                    ),
                    'p2'=>array(
                        'breadId'=>'3',
                        'amount'=>2
                    ),
                    'p3'=>array(
                        'breadId'=>'4',
                        'amount'=>1
                    )
                ),
                'z2'=>array(
                    'objType'=>1,
                    'goodId'=>'4',
                    'price'=>25,
                    'amount'=>2,
                    'freeQuantity'=>2, // 免费配菜数量
                    'p1'=>array(   // 配菜列表
                        'breadId'=>'3',
                        'amount'=>1
                    ),
                    'p2'=>array(
                        'breadId'=>'5',
                        'amount'=>1
                    ),
                    'p3'=>array(
                        'breadId'=>'7',
                        'amount'=>2
                    )
                )
            )
        );
        $jewishId=5;
        $objId=6;
        $memberId=4;
        $objType=1;
//
//        if(empty($jewishId)||empty($memberId)||empty($firstName)||empty($orderType)||empty($payment)||empty($name)||empty($amount)||empty($goodsPrice)||empty($objId)){
//            $rtnData['message']='提交订单失败。原因：订单信息不全';
//            $this->ajaxReturn($rtnData);
//        }
        // 订单编号
        $order_num = time().rand(10000,99999).'16'.rand(1000,9999);
        // 用户信息
        $memberList=M('member')->where("id=$memberId AND jewish_id=$jewishId")->find();
        // 订单总金额
        $orderDatailsData=array();
        if($objType=='1'){   // 食品
            $peiPrice=M('jewish')->where("id=$jewishId")->getField('pei_price');       // 得到配菜的价钱
//            $foodName=M('food')->where("id='$objId' AND jewish_id='$jewishId'")->getField('food_name');   // 得到食品名称
            // 计算食品费用
            $objIdList=array();   //商品或主菜id
            $goodCount=array();   // 每种主菜数量
            $goodSumMoney=0;   // 主菜金额
            $preeSumMoney=0;   // 配菜总金额
            $peiMoney=0;    // 主菜对应的配菜金额
            $preeListId=array();  // 配菜id 列表
            $preeListNum=array();  // 配菜数量 列表
            $goodPriceList=array();
            foreach($goodListExa['zList'] as $key=>$value){
                $objIdList[$key]=$value['goodId'];
                $goodCount[$key]=$value['amount'];
                $goodPriceList[$key]=$value['price'];    // 主菜单价
                $goodMoney=$value['amount']*$value['price'];   // 单个主菜的金额
                $preeCount=0;   // 对应的配菜数量
                foreach($value as $k=>$val){
                    if(is_array($val)){   // 有配菜
                        $preeCount[$key]=$val['amount'];    // 配菜个数累加
                        $peiMoney=($preeCount-$value['freeQuantity'])*$peiPrice;   // 每个主菜对应的配菜金额, money=（配菜个数-免费个数）*配菜单价
                        $preeListId[$key][]=$val['breadId'];
                        $preeListNum[$key][]=$val['amount'];
                    }
                    continue;
                }
                $preeSumMoney+=$peiMoney;   // 每个配菜的配菜金额累加 （配菜总金额）
                $goodSumMoney+=$goodMoney;  // 每个主菜金额累加 （主菜总金额）
            }
            $sumMoney= $goodSumMoney + $preeSumMoney;   // 总费用 = 主菜总金额 + 配菜总金额
//            var_dump($sumMoney);  die;
        }elseif($objType=='2'){   //  商品
//            $goodName=M('goods')->where("id='$objId'  AND jewish_id='$jewishId'")->getField('goods_name');   // 得到商品名称
            $goodSumMoney=0;   // 商品总金额
            $goodCount=array();     // 每一种商品的个数
            $goodPriceList=array();
            foreach($goodListExa['zList'] as $key=>$value){
                $objIdList[$key]=$value['goodId'];
                $goodPriceList[$key]=$value['price'];
                $goodCount[$key]=$value['amount'];
                $goodMoney=$value['amount']*$value['price'];   // 单个商品的金额
                $goodSumMoney+=$goodMoney;  // 每个商品金额累加 （商品总金额）
            }
            $sumMoney= $goodSumMoney;
//            $orderDatailsData['objCount']= $goodCount;
//            var_dump($sumMoney);  die;
        }

        // 订单折扣率
        if ($memberList) {
            $consume = $memberList['consume'];
            $mapcon['consume'] = array('elt', $consume);
            $mapcon['jewish_id'] = $jewishId;
            $mapcon['discount_type'] = $memberList['member_type'];   // 用户类型
            $mapcon['status'] = 1;   //折扣开启状态
            $discountCase = D('discount')->where($mapcon)->order('discount_rate desc')->find();
            $discRate = $discountCase['discount_rate'];
        }
        // 现金类型 1；人民币  2，美金
        if($memberList['money_type']==2){
            $poundage=M('exchange')->where("jewish_id=$jewishId")->getField('poundage');
            $payMoney=$sumMoney*((100-$discRate)/100)*(1+($poundage/100));    //实际支付金额，总金额 *  折扣率 * 美金折损率
        }else{
            $payMoney=$sumMoney*((100-$discRate)/100);    //实际支付金额，总金额 * 折扣率
        }
//


        $orderData=array(
            'jewish_id'=>$jewishId,
            'order_num'=>$order_num,
            'order_origin'=>1,   // 线上还是线下
            'member_id'=>$memberId,
//            'table_num'=>'',    //  餐桌编号
            'message'=>$goodListExa['message'],    // 备注信息
            'order_type'=>$goodListExa['orderType'],   //  堂吃/ 外卖
            'distribution'=>$goodListExa['distribution'],  // 配送方式
//            'is_pack'=>'',   // 打包
            'pick_date'=>$goodListExa['pickDate'],   // 配送日期
            'pick_time'=>$goodListExa['pickTime'],   // 配送时间
            'surname'=>$memberList['surname'],    // 姓氏
            'firstname'=>$memberList['firstname'],     // 名字
            'mobile'=>$memberList['mobile'],     // 手机号
            'site_info'=>$goodListExa['siteInfo'],
//            'site_id'=>,
            'longitude'=>$goodListExa['longitude'],    // 经度
            'latitude'=>$goodListExa['latitude'],     // 纬度
            'taxi_fare'=>$goodListExa['taxiFare'],   // 配送费
//            'pack_price_rmb'=>'',    // 打包费
//            'pack_price_dollar'=>'',   // 打包费（美金）
            'price_rmb'=>round($sumMoney,2),    // 订单金额
//            'price_dollar'=>'',
            'price_rmb_pay'=>round($payMoney,2),      // 实际支付金额
            'price_dollar_pay'=>'',
            'payment'=>$goodListExa['payment'],   //支付方式
            'pay_time'=>'',    // 支付时间
            'status'=>1,     // 支付状态
            'create_time'=>$orderTime  // 订单生成时间
        );


//        var_dump($sumMoney*((100-$discRate)/100));
//        var_dump($payMoney);
//        var_dump($sumMoney);
//        var_dump($payMoney/$sumMoney);
//        var_dump(round($payMoney,2));  die;
        // 订单明细表，插入多条数据
        $orderDetailsData=array();
        if($objType==1&&!empty($preeListId)&&!empty($preeListNum)){   // 食品时，有配菜
            foreach ($preeListId as $key=>$value){
                $preeId=implode(',',$value);
                $preeCountList=implode(',',$preeListNum[$key]);
                $goodAvgPrice=round($goodPriceList[$key]*($payMoney/$sumMoney),2);
                $orderDetailsData[$key]=array(
                    'order_num'=>$order_num,
                    'jewish_id'=>$jewishId,
                    'obj_type'=>$objType,
                    'obj_id'=>$objIdList[$key],
                    'amount'=>$goodCount[$key],        // 主菜/商品数量
                    'bread_ids'=>$preeId,       // 配菜id 多个时用逗号分隔
                    'bread_count'=>$preeCountList, // 配菜数量
                    'price_rmb'=>$goodAvgPrice,    // 平均单价
                    'price_dollar'=>''
                );
            }
        }else{      // 商品时
            foreach ($goodPriceList as $key=>$value){
                $goodAvgPrice=round($value*($payMoney/$sumMoney),2);
                $orderDetailsData[$key]=array(
                    'order_num'=>$order_num,
                    'jewish_id'=>$jewishId,
                    'obj_type'=>$objType,
                    'obj_id'=>$objIdList[$key],
                    'amount'=>$goodCount[$key],       // 主菜数量
                    'price_rmb'=>$goodAvgPrice,    // 平均单价
                    'price_dollar'=>''
                );
            }
        }
//        echo "<pre>";
//        print_r($goodListExa);
        $this->ajaxReturn($orderDetailsData);
        var_dump($orderDetailsData); die;
        var_dump($goodCountList);  die;

        // 插入数据库
        /*
        M()->startTrans();
        $inOrder=M('native_order')->data($orderData)->add();
        if($inOrder){
            $inOrderDet=M('order_details')->data($orderDetailsData)->add();
            if($inOrderDet){
                M()->commit();
                $rtnData['code']='200';
                $rtnData['message']='提交订单成功！';
            }else{
                $rtnData['code']='9202';
                $rtnData['message']='订单提交失败。原因：存储订单详情失败。';
            }
        }else{
            $rtnData['code']='9203';
            $rtnData['message']='订单提交失败。原因：存储订单失败。';
        }
        M()->rollback();
        */
        dbgLog($orderDetailsData);
    }

    // @10获取外卖自提/送达时间列表
    public function getOutTime()
    {
        $rtnData['code']='201';
        $rtnData['message']='获取外卖自提/送达时间列表失败';
        $jewishId=trim($_REQUEST['jewishId']);
        if($jewishId){
            $festivalList=M('festival')->where("jewish_id=$jewishId")->field('date_time')->select();   //规定的节日
            $jewishWorkTime=M('jewish')->where("id=$jewishId")->field('begin_time,end_time,sunday_begin_time,sunday_end_time')->find();
            if(!$festivalList||!$jewishWorkTime){
                $rtnData['message']='获取外卖自提/送达时间列表失败。原因：没查到该犹太中心，或没有设置营业时间';
                $this->ajaxReturn($rtnData);
            }
            foreach($festivalList as $value){
                $festival[]=date('Y-m-d',$value['date_time']);
            }
            foreach ($jewishWorkTime as $key=>$value){
                $jewishTime[$key]=date('H:i',$value);
            }
            $nowTimes=date('H:i',time());
            $nowDay=date('m-d',time());
            $nowTimeHours=date('H',time());
            $nowTimeMines=date('i',time());
            $lastTime=date('H:i',strtotime('+2 hours',strtotime($nowTimes)));
//            $lastTime="07:00";
            if($nowTimeMines>0 && $nowTimeMines<=15){
                $sendNowTimes=($nowTimeHours+2).':15';
            }elseif($nowTimeMines>15 && $nowTimeMines<=30){
                $sendNowTimes=($nowTimeHours+2).':30';
            }elseif($nowTimeMines>30 && $nowTimeMines<=45){
                $sendNowTimes=($nowTimeHours+2).':45';
            }elseif($nowTimeMines>45 && $nowTimeMines<=59){
                $sendNowTimes=($nowTimeHours+1+2).":00";
            }
            // 插入测试数据
//            $sundayEndTime=strtotime('+13 hours',$jewishWorkTime['begin_time']);
//            var_dump($sundayEndTime); die;
            $rtnData['data']=array();
            // 取出60天过后的日期，并剔除节假日
            for ($i=0;$i<60;$i++){
                $tempTime=date('Y-m-d',strtotime("+ $i days "));
                $tempWeek=date('w',strtotime($tempTime));
                if(in_array($tempTime,$festival)||$tempWeek=='5'||$tempWeek=='6')
                    continue;
                $workDay[]=date('Y-m-d',strtotime($tempTime));
            }
            // 得到当前时间过后的两个小时，并剔除不符合营业时间的，以及周日的营业时间
            foreach($workDay as $key=>$value){
                $thisDayWeek=date('w',strtotime($value));
                $thisDayDay=substr($value,5);
                $rtnData['data'][$key]['name']=$value;
//                $thisTimeAddTwo=strtotime('+2 hours',$nowTimes);
//                $thisDayWeek='1';
//                $thisDayDay='09-29';
                if($thisDayDay<=$nowDay){   // 今天的日期
                    if($thisDayWeek == '0'){   // 星期天
                        if($lastTime>$jewishTime['sunday_begin_time']&&$lastTime<=$jewishTime['sunday_end_time']){    // 营业时间内
                            $diffTime=sprintf('%.1f',intval(strtotime($jewishTime['sunday_end_time'])-strtotime($nowTimes))/3600);
                            $sumHours=intval($diffTime*4);
                            for ($i=0;$i<$sumHours;$i++){
                                $addTime=$i*15;
                                $addRightTime=($i+1)*15;
                                $leftTime=date('H:i',strtotime("+ $addTime  minutes",strtotime($sendNowTimes)));
                                $rightTime=date('H:i',strtotime("+ $addRightTime  minutes",strtotime($sendNowTimes)));
                                // 如果配送时间超出了营业结束时间，就跳过
                                if($rightTime>$jewishTime['sunday_end_time']||$leftTime<$jewishTime['sunday_begin_time']) continue;
                                $rtnData['data'][$key]['list'][]=$leftTime."-".$rightTime;
//                                $rtnData['data']['list'][$value]['name']=$value;
                            }
                            $rtnData['data'][$key]['list'][0]='立即送出（约两小时内）';
                        }elseif($lastTime>$jewishTime['sunday_end_time']){    // 超过了营业结束时间
                            continue;
                        }else{   // 没到营业时间
                            $diffTime=sprintf('%.1f',intval(strtotime($jewishTime['sunday_end_time'])-strtotime($jewishTime['sunday_begin_time']))/3600);
                            $sumHours=intval($diffTime*4);
                            for ($i=0;$i<$sumHours;$i++){
                                $addTime=$i*15;
                                $addRightTime=($i+1)*15;
                                $leftTime=date('H:i',strtotime("+ $addTime  minutes",strtotime('+2 hours',strtotime($jewishTime['sunday_begin_time']))));
                                $rightTime=date('H:i',strtotime("+ $addRightTime  minutes",strtotime('+2 hours',strtotime($jewishTime['sunday_begin_time']))));
                                // 如果配送时间超出了营业结束时间，就跳过
                                if($rightTime>$jewishTime['sunday_end_time']||$leftTime<$jewishTime['sunday_begin_time']) continue;
//                                $rtnData['data']['list'][$value]['name']=$value;
                                $rtnData['data'][$key]['list'][]=$leftTime."-".$rightTime;
                            }
                            $rtnData['data'][$key]['list'][0]='立即送出（约两小时内）';
                        }
                    }else{    // 不是星期日
                        if($lastTime>$jewishTime['begin_time']&&$lastTime<$jewishTime['end_time']){    // 营业时间段
                            $diffTime=sprintf('%.1f',intval(strtotime($jewishTime['end_time'])-strtotime($nowTimes))/3600);
                            $sumHours=intval(($diffTime*4));
                            for ($i=0;$i<$sumHours;$i++){
                                $addTime=$i*15;
                                $addRightTime=($i+1)*15;
                                $leftTime=date('H:i',strtotime("+ $addTime  minutes",strtotime($sendNowTimes)));
                                $rightTime=date('H:i',strtotime("+ $addRightTime  minutes",strtotime($sendNowTimes)));
                                // 如果配送时间超出了营业结束时间，就跳过
                                if($rightTime>$jewishTime['end_time']||$leftTime<$jewishTime['begin_time']) continue;
                                $rtnData['data'][$key]['list'][]=$leftTime."-".$rightTime;
                            }
                            $rtnData['data'][$key]['list'][0] = '立即送出（约两小时内）';
                        }elseif($lastTime>$jewishTime['end_time']){    // 超过了营业结束时间
                            continue;
                        }else {   // 没到营业时间
                            $diffTime = sprintf('%.1f', intval(strtotime($jewishTime['end_time']) - strtotime($jewishTime['begin_time'])) / 3600);
                            $sumHours = intval(($diffTime * 4));
                            for ($i = 0; $i < $sumHours; $i++) {
                                $addTime = $i * 15;
                                $addRightTime = ($i + 1) * 15;
                                $leftTime = date('H:i', strtotime("+ $addTime  minutes", strtotime('+2 hours', strtotime($jewishTime['begin_time']))));
                                $rightTime = date('H:i', strtotime("+ $addRightTime  minutes", strtotime('+2 hours', strtotime($jewishTime['begin_time']))));
                                // 如果配送时间超出了营业结束时间，就跳过
                                if ($rightTime > $jewishTime['end_time'] || $leftTime < $jewishTime['begin_time']) continue;
                                $rtnData['data'][$key]['list'][] = $leftTime . "-" . $rightTime;
//                                $rtnData['data']['list'][$value]['name']=$value;
                            }
                            $rtnData['data'][$key]['list'][0] = '立即送出（约两小时内）';
                        }
                    }
                }else{    // 今天以后的日期
                    if($thisDayWeek == '0'){   // 星期天？
                        $diffTime=sprintf('%.1f',intval(strtotime($jewishTime['sunday_end_time'])-strtotime($jewishTime['sunday_begin_time']))/3600);
                        $sumHours=intval($diffTime*4);
                        for ($i=0;$i<$sumHours;$i++){
                            $addTime=$i*15;
                            $addRightTime=($i+1)*15;
                            $leftTime=date('H:i',strtotime("+ $addTime  minutes",strtotime('+2 hours',strtotime($jewishTime['sunday_begin_time']))));
                            $rightTime=date('H:i',strtotime("+ $addRightTime  minutes",strtotime('+2 hours',strtotime($jewishTime['sunday_begin_time']))));
                            if($rightTime>$jewishTime['sunday_end_time']||$leftTime<$jewishTime['sunday_begin_time']) continue;
                            $rtnData['data'][$key]['list'][]=$leftTime."-".$rightTime;
                        }
                    }else{    // 不是星期日
                        $diffTime=sprintf('%.1f',intval(strtotime($jewishTime['end_time'])-strtotime($jewishTime['begin_time']))/3600);
                        $sumHours=intval($diffTime*4);
                        for ($i=0;$i<$sumHours;$i++) {
                            $addTime = $i * 15;
                            $addRightTime = ($i + 1) * 15;
                            $leftTime = date('H:i', strtotime("+ $addTime  minutes", strtotime('+2 hours',strtotime($jewishTime['begin_time']))));
                            $rightTime = date('H:i', strtotime("+ $addRightTime  minutes", strtotime('+2 hours',strtotime($jewishTime['begin_time']))));
                            // 如果配送时间超出了营业结束时间，就跳过
                            if ($rightTime > $jewishTime['end_time'] || $leftTime < $jewishTime['begin_time']) continue;
                            $rtnData['data'][$key]['list'][] = $leftTime . "-" . $rightTime;
                        }
                    }
                    $rtnData['data'][$key]['list'][0]='立即送出（约两小时内）';
                }
            }
            if(!empty($rtnData['data'])){
                $rtnData['code']='200';
                $rtnData['message']='获取外卖自提/送达时间列表成功';
            }
            $this->ajaxReturn($rtnData);
        }
    }

    /*
     *@11 获取收货地址列表
     * */
    public function getSiteList(){
        $memberId=trim($_REQUEST['memberId']);
        $jewishId=trim($_REQUEST['jewishId']);
        $rtnData['code']=201;
        $rtnData['message']='获取收货地址列表失败！';
        if(!empty($memberId) && !empty($jewishId)){
            $where['jewish_id']=$jewishId;
            $where['member_id']=$memberId;
            $siteList=M('site')->where($where)->select();
            if($siteList){
                $rtnData['code']=200;
                $rtnData['message']='获取收货地址列表成功！';
            }
            $rtnData['data']=getApiField($siteList,'siteId');
            $this->ajaxReturn($rtnData);
        }
    }

    /*
     * @12 新增收货地址
     * */
    public function doAddSite(){
        $rtnData['code']=201;
        $rtnData['message']='操作收货地址失败！';
        $jewishId=I('jewishId','');
        $memberId=I('memberId','');
        $defaultStatus=I('defaultStatus',0);
        $surName=I('surName','');
        $firstName=I('firstName','');
        $mobile=I('mobile','');
        $siteInfo=I('siteInfo','');
        $address=I('address','');
        $home=I('home','');
        $cityNo=I('cityNo','');
        $longitude=I('longitude',0);
        $latitude=I('latitude',0);
        $type=I('type','');
        $siteId=I('siteId','');

        $insertData['jewish_id']=$jewishId;
        $insertData['member_id']=$memberId;
        $insertData['default_status']=$defaultStatus;
        $insertData['surname']=$surName;
        $insertData['firstname']=$firstName;
        $insertData['mobile']=$mobile;
        $insertData['site_info']=$siteInfo;
        $insertData['address']=$address;
        $insertData['home']=$home;
        $insertData['longitude']=$longitude;
        $insertData['latitude']=$latitude;
        $jewish=M('jewish')->where("id=$jewishId")->find();
        $jewishCoor=$jewish['longitude'].",".$jewish['latitude'];
        $memberCoor=$longitude.",".$latitude;
        $taxiFare=$this->getTaxiCost($jewishCoor,$memberCoor,$cityNo);
        if($taxiFare)
            $insertData['taxi_fare']=$taxiFare;    // 配送价格
        if($type=='2'){   // 修改
            $status=M('site')->where("id=$siteId")->save($insertData);
            $message="收货地址修改成功！";
        }else{
            $status=M('site')->data($insertData)->add();
            $message="新增收货地址成功！";
        }
        if($status){
            $rtnData['code']=200;
            $rtnData['message']=$message;
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * @13 	获取shabbat列表
     * */
    public function getShabbatList(){
        $jewishId=I('jewishId','');
        $rtnData['code']=201;
        $rtnData['message']='获取shabbat列表失败！';
        $shabbatList=M('shabbat')->where("jewish_id=$jewishId")->select();
        if($shabbatList){
            $rtnData['code']=200;
            $rtnData['message']='获取shabbat列表成功！';
            $rtnData['data']=getApiField($shabbatList,'shabbatId');
//            var_dump($rtnData);  die;
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * @14 14.	获取shabbat菜品列表
     * */
    public function getShabbatFoods(){
        $shabbatId=I('shabbatId','');
        $rtnData['code']=201;
        $rtnData['message']='获取shabbat菜品失败！';
        $shabbatFood=M('shabbat_foods')->where("shabbat_id=$shabbatId")->select();
        if($shabbatFood){
            $rtnData['code']=200;
            $rtnData['message']='获取shabbat菜品列表成功！';
            $rtnData['data']=getApiField($shabbatFood);
        }
        $this->ajaxReturn($rtnData);
//        var_dump($shabbatFood);  die;
    }

    /*
     * 15.	shabbat预定
     * */
    public function doAddShabbatOrder(){
        $rtnData['code']=201;
        $rtnData['message']='shabbat预定失败！';
        $jewishId=I('jewishId','');
        $memberId=I('memberId','');
        $email=I('email','');
        $surname=I('surname','');
        $firstName=I('firstname','');
        $totalPrice=I('totalPrice','');
        $payment=I('payment','');
        $dateTime=I('dateTime','');
        $list=I('list','');
        $orderNum=time().rand(10000,99999);
        $order['jewish_id']=$jewishId;
        $order['order_num']=$orderNum;
        $order['member_id']=$memberId;
        $order['surname']=$surname;
        $order['firstname']=$firstName;
        $order['email']=$email;
        $order['price']=$totalPrice;
        $order['payment']=$payment;
        $order['status']='';
        $order['date_time']=$dateTime;
        $order['create_time']=time();
        $model=M('shabbat_order');
        $model->startTrans();
        $orderIns=$model->data($order)->add();
        if($orderIns){
            if(!empty($list)&&count($list)>0){
                foreach($list as $key=>$value){
                    $details['order_num']=$orderNum;
                    $details['shabbat_id']=$value['shabbatId'];
                    $details['price']=$value['price'];
                    $details['amount']=$value['amount'];
                    $detailsIns=M('shabbat_order_details')->data($details)->add();
                    if($detailsIns){
                        $model->commit();
                        $rtnData['code']=200;
                        $rtnData['message']='shabbat 预定成功！';
                    }else{
                        $model->rollback();
                    }
                }
            }
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *  16.	获取shabbat可预定日期
     * */
    public function getShabbatOrderTime(){
        $rtnData['code']=201;
        $rtnData['message']='获取 shabbat 可预定日期失败！';
        $jewishId=I('jewishId','');
        $festivalList=M('festival')->where("jewish_id=$jewishId")->getField("id,date_time");   //规定的节日
        foreach($festivalList as $val){
            $dateList[]=date('Y-m-d',$val);
        }
        for($i=0;$i<60;$i++){
            $thisDay=date('Y-m-d',strtotime("+ $i days"));
            $thisWeek=date('w',strtotime($thisDay));
            if(in_array($thisDay,$dateList)||$thisWeek<5){
                continue;
            }
            $rtnDateList[]=$thisDay;
        }
        if(count($rtnDateList)>0){
            $rtnData['code']=201;
            $rtnData['message']='获取 shabbat 可预定日期成功！';
            $rtnData['data']=$rtnDateList;
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 17.	获取超市商品分类列表
     * */
    public function getGoodsType(){
        $jewishId=I('jewishId','');
        $rtnData['code']=201;
        $rtnData['message']='获取超市商品分类列表失败！';
        $goodsType=M('goods_type')->where("jewish_id=$jewishId")->group('type_name')->select();
        if($goodsType){
            $rtnData['code']=200;
            $rtnData['message']='获取超市商品分类列表成功！';
            $rtnData['data']=getApiField($goodsType,'typeId');
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 18.	依据商品分类获取商品列表
     * */
    public function getGoodsList(){
        $jewishId=I('jewishId','');
        $typeId=I('typeId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',1);
        $rtnData['code']=201;
        $rtnData['message']='依据商品分类获取商品列表失败！';
        $goods=M('goods')->where("jewish_id=$jewishId AND type_id=$typeId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if($goods){
            $rtnData['code']=200;
            $rtnData['message']='依据商品分类获取商品列表成功！';
            $rtnData['data']=getApiField($goods);
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 19.	捐款    (支付方式待确认)
     * */
    public function doAddDonation(){
        $rtnData['code']=201;
        $rtnData['message']='捐款失败！';
        $memberId=I('memberId','');
        $jewishId=I('jewishId','');
        $money=I('money',0);
        $surname=I('surname','');
        $firstName=I('firstname','');
        $mobile=I('mobile','');
        $message=I('message','');
        $payment=I('payment','');
        $ins['member_id']=$memberId;
        $ins['jewish_id']=$jewishId;
        $ins['money']=$money;
        $ins['surname']=$surname;
        $ins['firstname']=$firstName;
        $ins['mobile']=$mobile;
        $ins['message']=$message;
        $ins['payment']=$payment;
        $ins['status']=1;
        $ins['create_time']=time();
        $status=M('donation')->data($ins)->add();
        if($status){
            $rtnData['code']=200;
            $rtnData['message']='捐款成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 20.	获取特殊活动列表
     * */
    public function getActivityList(){
        $jewishId=I('jewishId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',1);
        $rtnData['code']=201;
        $rtnData['message']='获取特殊活动列表失败！';
        $activity=M('activity')->where("jewish_id=$jewishId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if(!empty($activity)&&count($activity)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取特殊活动列表成功！';
            $rtnData['data']=getApiField($activity,'activityId');
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *  21.特殊活动报名
     * */
    public function doAddActivityEnroll(){
        $memberId=I('memberId','');
        $jewishId=I('jewishId','');
        $activityId=I('activityId','');
        $surname=I('surname','');
        $firstName=I('firstname');
        $mobile=I('mobile','');
        $amount=I('amount','');
        $rtnData['code']=201;
        $rtnData['message']='特殊活动报名失败！';
        $insData['activity_id']=$activityId;
        $insData['member_id']=$memberId;
        $insData['surname']=$surname;
        $insData['firstname']=$firstName;
        $insData['mobile']=$mobile;
//        $insData['message']=$;    // 留言
        $insData['amount']=$amount;
        $sta=M('activity_enroll')->data($insData)->add();
        if($sta){
            $rtnData['code']=200;
            $rtnData['message']='特殊活动报名成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 22.特殊需求预定
     *
     * */
    public function doAddSpecOrder(){
        $rtnData['code']=201;
        $rtnData['message']='特殊需求预定失败！';
        $memberId=I('memberId','');
        $jewishId=I('jewishId','');
        $message=I('message','');
        $surname=I('surname','');
        $firstName=I('firstname');
        $mobile=I('mobile','');
        $insData['jewish_id']=$jewishId;
        $insData['member_id']=$memberId;
        $insData['surname']=$surname;
        $insData['firstname']=$firstName;
        $insData['mobile']=$mobile;
        $insData['describe']=$message;
        $insData['create_time']=time();
        $sta=M('spec_order')->data($insData)->add();
        if($sta){
            $rtnData['code']=200;
            $rtnData['message']='特殊需求预定成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 23.获取我的订单列表
     * */
    public function getMyOrderList(){
        $rtnData['code']=201;
        $rtnData['message']='获取我的订单列表失败';
        $memberId=I('memberId','');
        $orderStatus=I('orderStatus',1);
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',1);
        $where['a.member_id']=$memberId;
        switch ($orderStatus){
            case 1:    // 待支付
                $where['a.payment']=0;
                break;
            case 2:    // 待确认
                $where['a.status']=array('in','2,3,4,5');
                break;
            case 3:   // 已确认
                $where['a.status']=6;
                break;
            case 4:   // 全部
                $where['a.status']=array('in','1,2,3,4,5,6');
                break;
            default:
                $where['a.payment']=0;
                break;
        }
        $orderList=M('native_order')->alias('a')->join('right join v_order_details b on a.order_num=b.order_num')->where($where)->limit($pageIndex*$pageSize,$pageSize)->select();
        $order=M('native_order')->alias('a')->field('id,jewish_id,order_num,order_origin,price_rmb_pay,status,create_time')->where($where)->limit($pageIndex,$pageSize)->select();
//        var_dump($order);  die;
        if(!empty($order)&&count($order)>0){
            $dtlField=array('obj_type,obj_id,bread_count,bread_ids,amount,price_rmb');
            foreach ($order as $key=>$value){
                $rtnData['data'][$key]['orderId']=$value['id'];    // 订单号
                $rtnData['data'][$key]['priceCount']=$value['price_rmb_pay'];   // 实付总价
                $rtnData['data'][$key]['status']=$value['status'];
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $commentWhere['member_id']=$memberId;
                $commentWhere['obj_type']=2;
                $commentWhere['obj_id']=$value['id'];
                $comment=M('comment')->where($commentWhere)->find();    // 订单是否已经评论
                if(!empty($comment)&&count($comment)>0){
                    $rtnData['data'][$key]['commentStatus']=1;
                }else{
                    $rtnData['data'][$key]['commentStatus']=2;
                }
                $dtlWhere['order_num']=$value['order_num'];
                $jshWhere['id']=$value['jewish_id'];
                $jewish=M('jewish')->where($jshWhere)->field('center_name,image_url')->find();   // 犹太中心名称、图片
                $rtnData['data'][$key]['jewishName']=$jewish['center_name'];
                $rtnData['data'][$key]['jewishUrl']=$jewish['image_url'];
                $orderDetails=M('order_details')->where($dtlWhere)->field($dtlField)->select();   // 订单详情
//                var_dump($orderDetails);  die;
                if (!empty($orderDetails)&&count($orderDetails)>0){
                    $rtnData['code']=200;
                    $rtnData['message']='获取我的订单列表成功！';
                    $goodCount=0;
                    foreach ($orderDetails as $ke=>$val){
                        $goodCount+=$val['amount'];   // 订单对应的商品总数量
                        // 订单下的商品列表
                        $objId=$val['obj_id'];
                        if($val['obj_type']==1){  //当前这个商品是菜品
                            $objName=M('food')->where("id=$objId")->getField('food_name');
                        }elseif ($val['obj_type']==2){  //当前这个商品是商品
                            $objName=M('goods')->where("id=$objId")->getField('goods_name');
                        }
                        $rtnData['data'][$key][$ke]['amount']=$val['amount'];
                        $rtnData['data'][$key][$ke]['price']=$val['price_rmb'];
                        $rtnData['data'][$key][$ke]['goodsName']=$objName;    // 动态获取商品名称
                        // 配菜列表
                        if($val['obj_type']==1 && !empty($val['bread_ids'])){
                            $breadId=explode(',',$val['bread_ids']);
                            $breadCount=explode(',',$val['bread_count']);
                            array_filter($breadId);
                            array_filter($breadCount);
                            foreach ($breadId as $k=>$v){   // 遍历配菜，获取菜名和数量
                                $breadName=M('food')->where("id=$v")->getField('food_name');
                                $rtnData['data'][$key][$ke][$k]['name']=$breadName;
                                $rtnData['data'][$key][$ke][$k]['amount']=$breadCount[$k];
                            }
                        }
                    }
                    $rtnData['data'][$key]['foodCount']=$goodCount;   // 订单对应的商品总数量
                    $rtnData['data'][$key]['content']=$objName."等".$goodCount."件商品";
                }
            }
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 24.获取订单详情
     * */
    public function getOrderDetails(){
        $orderId=I('orderId','');
        $memberId=I('memberId','');
        $where['id']=$orderId;
        $where['member_id']=$memberId;
        $rtnData['code']=201;
        $rtnData['message']='获取订单详情失败！';
        $dataInfo=M('native_order')->where($where)->find();
//        var_dump($dataInfo);  die;
        if($dataInfo){
            $rtnData['code']=200;
            $rtnData['message']='获取订单详情成功！';
            $commentStatus=M('comment')->where("obj_type=2 and member_id=$memberId and obj_id=$orderId")->find();
            $dataInfo['commentStatus']=$commentStatus>0?1:2;
            $rtnData['data']=getApiField($dataInfo);
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 25.查看我的订单评价
     * */
    public function getCommentInfo(){
        $orderId=I('orderId','');
        $memberId=I('memberId','');
        $where['a.obj_type']=2;
        $where['a.obj_id']=$orderId;
        $where['a.member_id']=$memberId;
        $rtnData['code']=201;
        $rtnData['message']='获取订单详情失败！';
        $comment=M('comment')->alias('a')->join('v_comment_reply b ON a.id=b.comment_id')->where($where)->find();
        if($comment){
            $rtnData['code']=200;
            $rtnData['message']='获取订单详情成功！';
            $memName=M('member')->where("id=$memberId")->field('surname,firstname')->find();
            $comment['createTime']=date('Y-m-d H:i:s',strtotime($comment['create_time']));
            $comment['account']=$memName['firstname']." ".$memName['surname'];
            $rtnData['data']=getApiField($comment);
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 26.取消订单
     * */
    public function doDelOrder(){
        $orderId=I('orderId','');
        $memberId=I('memberId','');
        $where['id']=$orderId;
        $where['member_id']=$memberId;
        $rtnData['code']=201;
        $rtnData['message']='取消订单失败！';
        $sta=M('native_order')->where($where)->setField('status','5');
        if($sta){
            $rtnData['code']=200;
            $rtnData['message']='取消订单成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 27.shabbat取消订单
     * */
    public function doDelShabbatOrder(){
        $orderId=I('orderId','');
        $memberId=I('memberId','');
        $where['id']=$orderId;
        $where['member_id']=$memberId;
        $rtnData['code']=201;
        $rtnData['message']='取消订单失败！';
        $sta=M('shabbat_order')->where($where)->setField('status','5');
        if($sta){
            $rtnData['code']=200;
            $rtnData['message']='取消订单成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 28.获取shabbat预定列表
     * */
    public function getMyShabbatOrderList(){
//        $orderNum=time().rand(1000,9999);
        $memberId=I('memberId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取shabbat预定列表失败！';
        $shabbat=M('shabbat_order')->where("member_id=$memberId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if(!empty($shabbat)&&count($shabbat)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取shabbat预定列表成功！';
            foreach ($shabbat as $key=>$value){
                $rtnData['data'][$key]['orderId']=$value['id'];
                $rtnData['data'][$key]['status']=$this->getCvsStatus($value['status']);
                $rtnData['data'][$key]['priceCount']=$value['price'];
                $rtnData['data'][$key]['payment']=$value['payment'];
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $dateTime=date('Y-m-d H:i:s',$value['date_time']);
                $jewishId=$value['jewish_id'];
                // 获取犹太中心相关信息
                $jewish=M('jewish')->where("id=$jewishId")->find();
                if($jewish){
                    $rtnData['data'][$key]['jewishName']=$jewish['center_name'];
                    $rtnData['data'][$key]['jewishUrl']=$jewish['image_url'];
                }
                // 获取每一个订单下的活动信息
                $orderNum=$value['order_num'];
                $shabbatDtl=M('shabbat_order_details')->where("order_num=$orderNum")->select();
                if (count($shabbatDtl)>0){
                    foreach ($shabbatDtl as $ke=>$val){
                        $rtnData['data'][$key][$ke]['dateTime']=$dateTime;
                        $rtnData['data'][$key][$ke]['amount']=$val['amount'];
                        $rtnData['data'][$key][$ke]['priceRmb']=$val['price_rmb'];
                        // 获取shabbat 名称
                        $shabbatId=$val['shabbat_id'];
                        $shabbatName=M('shabbat')->where("id=$shabbatId")->getField('shabbat_name');
                        $rtnData['data'][$key][$ke]['shabbatName']=$shabbatName;
                    }
                }
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='当前用户没有shabbat订单！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 29.获取堂吃预定列表
     * */
    public function getMyEatenList(){
        $memberId=I('memberId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取堂吃预定列表失败';
        $eatenPrede=M('eaten_prede')->where("member_id=$memberId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if (!empty($eatenPrede)&&count($eatenPrede)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取堂吃预定列表成功！';
            foreach($eatenPrede as $key=>$value){
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $rtnData['data'][$key]['peopleNum']=$value['people_num'];
                $rtnData['data'][$key]['dateTime']=date('Y-m-d',$value['date_time'])." ".date('H:i:s',$value['hours_time']);
                $rtnData['data'][$key]['status']=$value['status'];
                $rtnData['data'][$key]['mobile']=$value['mobile'];
                $rtnData['data'][$key]['id']=$value['id'];
                $jwhId=$value['jewish_id'];
                $jewishInfo= M('jewish')->where("id=$jwhId")->find();
                $rtnData['data'][$key]['jewishName']=$jewishInfo['center_name'];
                $rtnData['data'][$key]['jewishUrl']=$jewishInfo['image_url'];
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='当前用户没有订单！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 30.获取特殊需求预定列表
     * */
    public function getMySpecList(){
        $memberId=I('memberId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取特殊需求预定列表失败';
        $specList=M('spec_order')->where("member_id=$memberId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if (!empty($specList)&&count($specList)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取特殊需求预定列表成功！';
            foreach($specList as $key=>$value){
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $rtnData['data'][$key]['describe']=$value['describe'];
                $rtnData['data'][$key]['status']=$value['status'];
                $rtnData['data'][$key]['mobile']=$value['mobile'];
                $rtnData['data'][$key]['surname']=$value['surname'];
                $rtnData['data'][$key]['firstname']=$value['firstname'];
                $rtnData['data'][$key]['id']=$value['id'];
                $jwhId=$value['jewish_id'];
                $jewishInfo= M('jewish')->where("id=$jwhId")->find();
                $rtnData['data'][$key]['jewishName']=$jewishInfo['center_name'];
                $rtnData['data'][$key]['jewishUrl']=$jewishInfo['image_url'];
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='此用户没有特殊需求预定！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 31.获取我已报名活动列表
     * */
    public function getMyActivityList(){
        $memberId=I('memberId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取我已报名活动列表失败';
        $myActiv=M('activity_enroll')->where("member_id=$memberId")->limit($pageIndex*$pageSize,$pageSize)->select();
//        var_dump($myActiv);  die;
        if (!empty($myActiv)&&count($myActiv)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取我已报名活动列表成功！';
            foreach($myActiv as $key=>$value){
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $rtnData['data'][$key]['amount']=$value['amount'];
                $rtnData['data'][$key]['status']=$value['status'];
                $rtnData['data'][$key]['message']=$value['message'];
                $rtnData['data'][$key]['id']=$value['id'];
                $myActivityId=$value['activity_id'];
                $activityInfo= M('activity')->where("id=$myActivityId")->find();
                $rtnData['data'][$key]['activityName']=$activityInfo['activity_name'];
                $rtnData['data'][$key]['content']=$activityInfo['content'];
                $jewishId=$activityInfo['jewish_id'];
                $jewishInfo=M('jewish')->where("id=$jewishId")->find();
                $rtnData['data'][$key]['jewishName']=$jewishInfo['center_name'];
                $rtnData['data'][$key]['jewishUrl']=$jewishInfo['image_url'];
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='此用户没有报名任何活动！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *  32.获取我的捐款记录列表
     * */
    public function getMyDonationList(){
        $memberId=I('memberId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取我的捐款记录列表失败';
        $donation=M('donation')->where("member_id=$memberId")->limit($pageIndex*$pageSize,$pageSize)->select();
        if (!empty($donation)&&count($donation)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取我的捐款记录列表成功！';
            foreach($donation as $key=>$value){
                $rtnData['data'][$key]['createTime']=date('Y-m-d H:i:s',$value['create_time']);
                $rtnData['data'][$key]['status']=$value['status'];
                $rtnData['data'][$key]['message']=$value['message'];
                $rtnData['data'][$key]['payment']=$value['payment'];
                $rtnData['data'][$key]['money']=$value['money'];
                $rtnData['data'][$key]['surname']=$value['surname'];
                $rtnData['data'][$key]['firstname']=$value['firstname'];
                $rtnData['data'][$key]['mobile']=$value['mobile'];
                $rtnData['data'][$key]['id']=$value['id'];
                $jewishId=$value['jewish_id'];
                $jewishInfo=M('jewish')->where("id=$jewishId")->find();
                $rtnData['data'][$key]['jewishName']=$jewishInfo['center_name'];
                $rtnData['data'][$key]['jewishUrl']=$jewishInfo['image_url'];
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='此用户没有捐款记录！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 33.获取APP最新版本信息
     * */
    public function getLastVersion(){
        $rtnData['code']=201;
        $rtnData['message']='获取APP最新版本信息失败';
        $version=M('version')->order('create_time desc')->find();
        if (!empty($version)){
            $rtnData['code']=200;
            $rtnData['message']='获取APP最新版本信息成功';
            $rtnData['data']['versionNum']=$version['version_num'];
            $rtnData['data']['versionName']=$version['version_name'];
            $rtnData['data']['downloadUrl']=$version['download_url'];
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 34.获取关于我们信息
     * */
    public function getJewishAbout(){
        $rtnData['code']=201;
        $rtnData['message']='获取关于我们信息失败';
        $version=M('info')->order('create_time desc')->find();
        if (!empty($version)){
            $rtnData['code']=200;
            $rtnData['message']='获取关于我们信息成功';
            $rtnData['data']['title']=$version['title'];
            $rtnData['data']['content']=$version['content'];
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *35 修改密码
     * */
    public function doSavePassword(){
        $rtnData['code']=201;
        $rtnData['message']='修改密码失败！';
        $memberId=I('memberId','');
        $oldPassword=I('password','');
        $password=I('password2','');
        $where['id']=$memberId;
        $lastPassword=M('member')->where($where)->getField('password');
        if(md5($oldPassword)!=$lastPassword){
            $rtnData['code']=203;
            $rtnData['message']='你输入的原密码错误！';
        }else{
            $passCheck=$this->passCheck($password);
            if(!$passCheck){
                $rtnData['code']=207;
                $rtnData['message']='密码必须是大于6位数，且必须同时含有字母和数字！';
            }else{
                $password=md5($password);
                $update=M('member')->where($where)->setField('password',$password);
                if($update){
                    $rtnData['code']=200;
                    $rtnData['message']='修改密码成功！';
                }
            }
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *  36 获取所有犹太中心列表
     * */
    public function getJewishList(){
        $rtnData['code']=201;
        $rtnData['message']='获取所有犹太中心列表失败！';
        $jewish=M('jewish')->field('id jewishId,center_name jewishName')->select();
        if(!empty($jewish)&&count($jewish)>0){
            $rtnData['code']=200;
            $rtnData['message']='获取所有犹太中心列表成功！';
            $rtnData['data']=$jewish;
        }else{
            $rtnData['message']='目前没有犹太中心！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 37 修改用户货币结算单位
     * */
    public function doSaveMoneyType(){
        $memberId=I('memberId','');
        $moneyType=I('moneyType','');
        $rtnData['code']=201;
        $rtnData['message']='修改用户货币结算单位失败！';
        $mmType=M('member')->where("id=$memberId")->getField('money_type');
        $upSta=M('member')->where("id=$memberId")->setField('money_type',$moneyType);
        if($upSta||$mmType==$moneyType){
            $rtnData['code']=200;
            $rtnData['message']='修改用户货币结算单位成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *38. 获取用户个人信息
     * */
    public function getUserInfo()
    {
        $memberId=I('memberId','');
        $jewishId=I('jewishId','');
        $rtnData['code']=201;
        $rtnData['mesage']='获取用户个人信息失败！';
        $field=array('id'=>'memberId','jewish_id'=>'jewishId','email','mobile','member_type'=>'memberType','money_type'=>'moneyType','consume');
        $member=M('member')->where("id=$memberId")->field($field)->find();
        if($member){
            $rtnData['code']=200;
            $rtnData['mesage']='获取用户个人信息成功！';
            $rtnData['data']=$member;
            $rtnData['data']['account']=empty($member['mobile'])?$member['email']:$member['mobile'];
            $memberType=$member['memberType'];   // 用户身份
            $consume=$member['consume'];    // 累计消费金额
            if($memberType=='2'){   // 学生
                $disWhere['discount_type']=1;
                $disWhere['jewish_id']=$jewishId;
                $disSta=M('discount')->where($disWhere)->getField('status');
                if($disSta=='2'){   // 学生折扣关闭了
                    $disWhere['discount_type']=2;   // 改为普通折扣
                    $disWhere['status']=1;      // 已开启的最大折扣
                    $disWhere['consume']=array('elt',$consume);
                }
            }else{  // 普通用户
                $disWhere['discount_type']=2;
                $disWhere['jewish_id']=$jewishId;
                $disWhere['status']=1;
                $disWhere['consume']=array('elt',$consume);
            }
            $discont=M("discount")->where($disWhere)->order('consume desc')->find();
            if(!empty($discont)&&$discont['discount_rate']>0&&$discont['level']>0){
                $rtnData['data']['discountRate']=($discont['discount_rate'])/100;
                $rtnData['data']['level']=$discont['level'];
            }else{
                $rtnData['data']['discountRate']=0;
                $rtnData['data']['level']=0;
            }
        }else{
            $rtnData['code']=404;
            $rtnData['mesage']='找不到此用户的个人信息！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * 39.获取安息日说明信息
     * */
    public function getSabbathInfo(){
        $jewishId=I('jewishId','');
        $rtnData['code']=201;
        $rtnData['message']='获取安息日说明信息失败！';
        $jewishExp=M('jewish')->where("id=$jewishId")->getField('shabbat_explain');
        if(!empty($jewishExp)){
            $rtnData['code']=200;
            $rtnData['message']='获取安息日说明信息成功！';
            $rtnData['data']['content']=$jewishExp;
        }else{
            $rtnData['code']=404;
            $rtnData['message']='此犹太中心没有安息日说明！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *40 获取折扣列表信息
     * */
    public function getDiscoutList(){
        $jewishId=I('jewishId','');
        $where['jewish_id']=$jewishId;
        $where['status']=1;
        $discount=M("discount")->where($where)->select();
        if($discount){
            $rtnData['code']=200;
            $rtnData['message']='获取折扣列表信息成功！';
            foreach ($discount as $key=>$value){
                $rtnData['data'][$key]['discountName']=$value['discount_name'];
                $rtnData['data'][$key]['level']=$value['level'];
                $rtnData['data'][$key]['discountRate']=$value['discount_rate'];
                $rtnData['data'][$key]['consume']=$value['consume'];
            }
        }else{
            $rtnData['code']=404;
            $rtnData['message']='获取折扣列表信息失败：此犹太中心没有指定折扣率。';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * @41 获取首页评论信息列表
     * */
    public function getDiscussList(){
        $jewishId=I('jewishId','');
        $pageIndex=I('pageIndex',0);
        $pageSize=I('pageSize',10);
        $rtnData['code']=201;
        $rtnData['message']='获取首页评论信息列表失败！';
        $where['a.jewish_id']=$jewishId;
        $where['a.status']=1;
        $where['a.obj_type']=1;
        $where['a.obj_id']=$jewishId;
        $field=array(
          'a.comment_content'=>'commentContent',
            'a.star_level'=>'starLevel',
            'a.member_id'=>'memberId',
            'a.comment_content'=>'commentContent',
            'a.create_time'=>'createTime',
            'b.reply_content'=>'replyContent'
        );
        $comment=M('comment')->alias('a')->field($field)->join('left join v_comment_reply b ON a.id=b.comment_id')->where($where)->limit($pageIndex*$pageSize,$pageSize)->order('a.create_time desc')->select();
        if($comment){
                $rtnData['code']=200;
                $rtnData['message']='获取首页评论信息列表成功！';
                foreach ($comment as $value){
                    $currMemberId=$value['memberId'];
                    $member=M('member')->where("id=$currMemberId")->find();
                    if($member['mobile']){
                        $value['account']=substr($member['mobile'],0,3)."*****".substr($member['mobile'],-3);
                    }else{
                        $end=strpos($member['email'],'@');
                        $value['account']=substr($member['email'],0,3)."******".substr($member['email'],$end);
                    }
                    $value['createTime']=date("Y-m-d H:i:s",$value['createTime']);
                    $rtnData['data'][]=$value;
                }
        }else{
            $comCount=M('comment')->where("obj_type=1 and obj_id=$jewishId and status=1")->limit(0,10)->select();
            if($pageIndex>0&&count($comCount)>0){
                $rtnData['code']=202;
                $rtnData['message']='没有更多评论信息了！';
            }else{
                $rtnData['code']=204;
                $rtnData['message']='此犹太中心没有评论！';
            }
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     *  @42.提交评价
     * */
    public function doAddComment(){
        $rtnData['code']=201;
        $rtnData['message']='评价失败！';
        $jewishId=I('jewishId','');
        $memberId=I('memberId','');
        $objType=I('objType','');
        $objId=I('objId','');
        $starLevel=I('starLevel','');
        $commentContent=I('commentContent','');
        $insData['jewish_id']=$jewishId;
        $insData['member_id']=$memberId;
        $insData['obj_type']=$objType;
        $insData['obj_id']=$objId;
        if($objType=='2'){  // 如果是订单，不允许再次评价
            $exitComment=M('comment')->where($insData)->select();
            if($exitComment){    // 订单是否已经评价过
                $rtnData['code']=205;
                $rtnData['message']='你已经评价过此订单！';
                $this->ajaxReturn($rtnData);
            }
        }
        $insData['star_level']=$starLevel;
        $insData['comment_content']=$commentContent;
        $insData['create_time']=time();
        $insStatus=M('comment')->data($insData)->add();
        if($insStatus){
            $rtnData['code']=200;
            $rtnData['message']='评价成功！';
        }
        $this->ajaxReturn($rtnData);
    }

    /*
     * @43 43.堂吃预定
     * */
    public function addEatenPrede(){
        $memberId=I('memberId','');
        $jewishId=I('jewishId','');
        $peopleNum=I('peopleNum','');
        $mobile=I('mobile','');
        $predeDate=I('predeDate','');
        $predeTime=I('predeTime','');
        $predeType=I('predeType','');
        $predeId=I('predeId','');
        $rtnData['code']=201;
        $rtnData['message']='堂吃预定失败！';
        if(empty($memberId)||empty($jewishId)){
            $rtnData['code']=205;
            $rtnData['message']='必备参数不全！';
            $this->ajaxReturn($rtnData);
        }
        $memberInfo=M('member')->where("id=$memberId")->find();
        $data=array(
            'jewish_id'=>$jewishId,
            'member_id'=>$memberId,
            'people_num'=>$peopleNum,
            'mobile'=>$mobile,
            'date_time'=>$predeDate,
            'hours_time'=>$predeTime,
            'surname'=>$memberInfo['surname'],
            'firstname'=>$memberInfo['firstname'],
            'create_time'=>time()
        );
        if($predeType=='2'){    // 编辑
            $editWhere=array('id'=>$predeId);
            $add=M('eaten_prede')->where($editWhere)->save($data);
        }else{      // 新增
            $where=array('jewish_id'=>$jewishId,
                'member_id'=>$memberId,'date_time'=>$predeDate,
                'hours_time'=>$predeTime);
            $existed=M('eaten_prede')->where($where)->select();
            if($existed){     //  如果此时段已经预定过
                $rtnData['code']=206;
                $rtnData['message']='此时段内你已经预定过，请到个人中心查看你的预定！';
                $this->ajaxReturn($rtnData);
            }else{    // 新增
                $add=M('eaten_prede')->data($data)->add();
            }
        }
        if($add){
            $rtnData['code']=200;
            $rtnData['message']='堂吃预定成功！';
        }
        $this->ajaxReturn($rtnData);
    }
    /*
     * 插入测试评论
     * */
    public function addComment(){
        $comment_content='非常好，值得购买，味道很棒，赞.?下次还来!; $1234567890';
        $arr=array('非常好','值得购买','味道很棒','食品很赞','good','再接再厉','oh');
        $strLeng=strlen($comment_content);
        for ($i=0;$i<10;$i++){
            $data['jewish_id']=5;
            $data['member_id']=rand(3,9);
            $data['obj_type']=1;
            $data['obj_id']=5;
            $data['star_level']=rand(1,5);
            $data['create_time']=time();
            $key=rand(0,6);
            $data['comment_content']=$arr[$key];
            M('comment')->add($data);
        }
    }
    /*
     * 转换订单状态
     * 数据库 =》接口
     * 1.待确认  =》2.待确认
     * 2.制作中 3.配送中 4.已退款 5.已取消  =》3.已确认
     * 6.已完成 =》4.已完成
     * */
    public function getCvsStatus($status=1){
        if(empty($status)) return false;
        switch ($status){
            case 1:
                $status =2;
                break;
            case 2:
            case 3:
            case 4:
            case 5:
                $status=3;
                break;
            case 6:
                $status=4;
                break;
            default:
                $status=1;
        }
        return $status;
    }

    /*
     * 密码规则检查
     * */
    public function passCheck($pass=0){
        // 必须是字母和数字的组合，或者含有特殊字符
        if(preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z\S]{6,18}$/',$pass)){
            return true;
        }else{
            return false;
        }
    }

    /*
     *   获取打车费用（高德）
     *  $origin : 出发点  lon,lat（经度,纬度）  121.426001,31.227923
     * $destination  目的地  规则： lon,lat（经度,纬度）    121.401307,31.218754
     * $city 城市/跨城规划时的起点城市  可选值：城市名称/citycode
     * $extensions  可选值：base(default)/all   base:返回基本信息；all：返回全部信息
     * */
    public function getTaxiCost($origin='',$destination='',$city='021',$extensions='base'){
        $key=C('MAP_KEY');
        if(empty($origin)||empty($destination)) return false;
        $origin='121.426001,31.227923';
        $destination='121.401307,31.218754';
        $url="http://restapi.amap.com/v3/direction/transit/integrated?origin=$origin&destination=$destination&city=$city&output=xml&key=$key";
        $curl = curl_init();   //创建curl
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $return_str = curl_exec($curl);    //执行
        curl_close($curl);   //释放
        preg_match("@<taxi_cost>(.*?)</taxi_cost>@i",$return_str,$taxi);
        if($taxi){
            $taxiCost=round($taxi[1],2);
            return $taxiCost;
        }
        return false;
    }
    /*
     * 订单处理
     */
    public function order()
    {
        self::doalipay();
    }

    //支付宝支付 ----即使到账接口
    public function doalipay($data = ''){
        //通过TP的C函数把配置项参数读出，赋给$alipay_config；
        $alipay_config=C('alipay_config');

        /**************************请求参数**************************/
        $payment_type = "1"; //支付类型 //必填，不能修改
        $notify_url =C('alipay.notify_url'); //服务器异步通知页面路径
        $return_url =C('alipay.return_url'); //页面跳转同步通知页面路径
        $seller_id = C('alipay.seller_id');//卖家支付宝帐户必填
        $out_trade_no = '147125521255233633345119';//商户订单号 通过支付页面的表单进行传递，注意要唯一！
        $subject = 'shabbat周五晚餐';  //订单名称 //必填 通过支付页面的表单进行传递
        $total_fee = '0.01';//$_POST['ordtotal_fee'];   //付款金额  //必填 通过支付页面的表单进行传递
        $body = '';//$_POST['ordbody'];  //订单描述 通过支付页面的表单进行传递
        $show_url = 'http://www.songdiankeji.com/jewish/index.php/Home/Index';        //$_POST['ordshow_url'];  //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key = "";                    //防钓鱼时间戳 //若要使用请调用类文件submit中的query_timestamp函数
        $exter_invoke_ip = get_client_ip();         //客户端的IP地址

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($alipay_config['partner']),
            "payment_type"  => $payment_type,
            "notify_url"    => $notify_url,
            "return_url"    => $return_url,
            "seller_id"     => $seller_id,
            "out_trade_no"  => $out_trade_no,
            "subject"       => $subject,
            "total_fee"     => $total_fee,
            "body"          => $body,
            "show_url"      => $show_url,
            "anti_phishing_key"    => $anti_phishing_key,
            "exter_invoke_ip"      => $exter_invoke_ip,
            "_input_charset"       => trim(strtolower($alipay_config['input_charset']))
        );

        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $parameter = $alipaySubmit->buildRequestPara($parameter);
        $html_text = $alipaySubmit->buildRequestForm($parameter,"post", "确认");
        echo $html_text;
    }

    /******************************
    服务器异步通知页面方法
    其实这里就是将notify_url.php文件中的代码复制过来进行处理

     *******************************/
    function notifyurl(){
        /*
        同理去掉以下两句代码；
        */
        //require_once("alipay.config.php");
        //require_once("lib/alipay_notify.class.php");

        //这里还是通过C函数来读取配置项，赋值给$alipay_config
        $alipay_config=C('alipay_config');
        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config); // AlipayNotify($alipay_config)
        $verify_result = $alipayNotify->verifyNotify();
        if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $out_trade_no   = $_POST['out_trade_no'];      //商户订单号
            $trade_no       = $_POST['trade_no'];          //支付宝交易号
            $trade_status   = $_POST['trade_status'];      //交易状态
            $total_fee      = $_POST['total_fee'];         //交易金额
            $notify_id      = $_POST['notify_id'];         //通知校验ID。
            $notify_time    = $_POST['notify_time'];       //通知的发送时间。格式为yyyy-MM-dd HH:mm:ss。
            $buyer_email    = $_POST['buyer_email'];       //买家支付宝帐号；
            $parameter = array(
                "out_trade_no"     => $out_trade_no, //商户订单编号；
                "trade_no"     => $trade_no,     //支付宝交易号；
                "total_fee"     => $total_fee,    //交易金额；
                "trade_status"     => $trade_status, //交易状态
                "notify_id"     => $notify_id,    //通知校验ID。
                "notify_time"   => $notify_time,  //通知的发送时间。
                "buyer_email"   => $buyer_email,  //买家支付宝帐号；
            );
            if($_POST['trade_status'] == 'TRADE_FINISHED') {
                //
            }else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                /*if(!checkorderstatus($out_trade_no)){
                    orderhandle($parameter);//付款成功后将数据添加到数据库
                    //进行订单处理，并传送从支付宝返回的参数；
                }*/
                $this->dosaveorder();
            }
            echo "success";        //请不要修改或删除
        }else {
            //验证失败
            echo "fail";
        }
    }

    /*
        页面跳转处理方法；
        这里其实就是将return_url.php这个文件中的代码复制过来，进行处理；
        */
    function returnurl(){
        //头部的处理跟上面两个方法一样，这里不罗嗦了！
        $alipay_config=C('alipay_config');
        $alipayNotify = new \AlipayNotify($alipay_config);//计算得出通知验证结果
        $verify_result = $alipayNotify->verifyReturn();
        if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
            $out_trade_no   = $_GET['out_trade_no'];      //商户订单号
            $trade_no       = $_GET['trade_no'];          //支付宝交易号
            $trade_status   = $_GET['trade_status'];      //交易状态
            $total_fee      = $_GET['total_fee'];         //交易金额
            $notify_id      = $_GET['notify_id'];         //通知校验ID。
            $notify_time    = $_GET['notify_time'];       //通知的发送时间。
            $buyer_email    = $_GET['buyer_email'];       //买家支付宝帐号；

            $parameter = array(
                "out_trade_no"     => $out_trade_no,      //商户订单编号；
                "trade_no"     => $trade_no,          //支付宝交易号；
                "total_fee"      => $total_fee,         //交易金额；
                "trade_status"     => $trade_status,      //交易状态
                "notify_id"      => $notify_id,         //通知校验ID。
                "notify_time"    => $notify_time,       //通知的发送时间。
                "buyer_email"    => $buyer_email,       //买家支付宝帐号
            );

            if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
                /*if(!checkorderstatus($out_trade_no)){
                    orderhandle($parameter);  //进行订单处理，并传送从支付宝返回的参数；
                }*/
                $this->dosaveorder();
                $this->redirect(C('alipay.successpage'));//跳转到配置项中配置的支付成功页面；
            }else {
                echo "trade_status=".$_GET['trade_status'];
                $this->redirect(C('alipay.errorpage'));//跳转到配置项中配置的支付失败页面；
            }
        }else {
            //验证失败
            //如要调试，请看alipay_notify.php页面的verifyReturn函数
            echo "支付失败！";
        }
    }

    //支付成功修改订单
    public function dosaveorder(){

    }
}