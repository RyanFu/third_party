<?php
/**
微信支付回调的方法
在扫码模式二测试通过
参考文档：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_7
**/

     public function wx_notify(){
        \App\Log::write('开始回调');
        //获取通知的数据
		//原生办法
        //$xml = file_get_contents('php://input');
		//swoole办法
        $xml = $this->request->body;
        $data = array();
        require_once('./weixinpay/WxPay.Data.php');
        $notify = new \WxPayDataBase();
        $result = $notify->FromXml($xml);
        $notify_model = model('WxNotify');
        $arr_params['appid']          = trim($result['appid']);
        $arr_params['attach']         = trim($result['attach']);
        $arr_params['bank_type']      = trim($result['bank_type']);
        $arr_params['cash_fee']       = trim($result['cash_fee']);
        $arr_params['fee_type']       = trim($result['fee_type']);
        $arr_params['is_subscribe']   = trim($result['is_subscribe']);
        $arr_params['mch_id']         = trim($result['mch_id']);
        $arr_params['nonce_str']      = trim($result['nonce_str']);
        $arr_params['openid']         = trim($result['openid']);
        $arr_params['out_trade_no']   = trim($result['out_trade_no']);
        $arr_params['result_code']    = trim($result['result_code']);
        $arr_params['return_code']    = trim($result['return_code']);
        $arr_params['sign']           = trim($result['sign']);
        $arr_params['time_end']       = trim($result['time_end']);
        $arr_params['total_fee']      = trim($result['total_fee']);
        $arr_params['trade_type']     = trim($result['trade_type']);
        $arr_params['transaction_id'] = trim($result['transaction_id']);
        if(!$notify_model->put($arr_params)){
            \App\Log::write( "添加回调结果失败");
            return $this->array_message($data,503);
        }
        unset($arr_params);
        if(array_key_exists('result_code', $result) && $result['result_code'] == 'SUCCESS') {
            $order_sn = isset($result['out_trade_no']) ? $result['out_trade_no'] : '';
            if (!empty($order_sn)) {
                $order_model = model('Order');
                $arr_result = $order_model->get_one_by_where(array('order_sn' => $order_sn, 'select' => 'status'));
                if (empty($arr_result)) {
                    \App\Log::write('该订单不存在' . $order_sn);
                    $this->http->finish("回调exit");
                }
                $status = intval($arr_result['status']);
                if (1 <> $status) {
                    \App\Log::write("{$order_sn}该订单不是下单状态，目前是{$status}状态");
                    $this->http->finish("回调exit");
                }
                unset($arr_result);
                $arr_params['status'] = 2;
                if (!$order_model->sets($arr_params, array('order_sn' => $order_sn))) {
                    \App\Log::write("订单审核更新失败：【{$order_sn}】");
                    $this->http->finish("回调exit");
                }
                unset($arr_params);
                //回应微信支付服务器，让其不再执行回调
                unset($result);
                echo $notify->ToXml();
            }
        }
    }