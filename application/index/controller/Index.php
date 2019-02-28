<?php
namespace app\index\controller;

use think\Controller;
class Index  extends Controller {
    public function index() {
       return $this->fetch(); 
    }

    public function add() {
        $data = input('post.');

        $newCard = [];
        $newCard['card'] = $data['card'];
        $newCard['password'] = $data['password'];

        model("Card")->save($newCard);

        return $this->success('新增成功', 'index/index/index');
    }

    public function query() {
        $card = input('get.card');

        $cards = model('Card')->where("card", "like", "%".$card."%")->select();
        
        if ($card) {
            $this->assign('cards', $cards);
            return $this->fetch();
        } else {
            return $this->error("不存在的卡号");
        }
    }

    public function up() {
        $newUp = [];

        $card = input('post.card');

        $the_user = model('Card')->get(['card' => $card, 'password' => input('post.password')]);

        if ( $the_user ) {
            if ($the_user->status == -1) {
                return $this->error('用户已注销');
            } else {
                $the_user_online = model('CardOnline')->where('card', '=', input('post.card'))->where('status', '=', '0')->select();
                if (! (count($the_user_online)==0)) {
                    return $this->error('用户已上机');
                }
            }
        } else {
            return $this->error('用户名不存在或密码错误');
        }

        $newUp['card'] = $card;    
        $newUp['start_time']  = strtotime('now');

        model('CardOnline')->save($newUp);
        
        $the_user->times = $the_user->times + 1;

        $the_user->save();

        return $this->success('上机成功');
    }

    public function down() {
        $the_user = model('Card')->get(['card' => input('post.card'), 'password' => input('post.password')]);

        if (! $the_user) {
            return $this->error('用户名或密码错误');
        }

        $the_user_online = model('CardOnline')->where('card', '=', input('post.card'))->where('status', '=', '0')->select();
        
        if ( (count($the_user_online) == 0) ) {
            return $this->error('用户未上机');
        }

        $the_user_online = $the_user_online[0];


        $end_time = strtotime('now');
        $the_user_online->end_time = $end_time;

        $total_time = $end_time - $the_user_online->start_time;
        $total_time = $total_time  / (60 * 60); //转化为小时

        $money_used = 5 * $total_time;  // 5元每个小时
        $the_user_online->money_used = $money_used; 

        $the_user_online->status = -1;
        $the_user_online->save();

        $the_user->balance = $the_user->balance - $money_used;
        $the_user->total_used = $the_user->total_used + $money_used;
        $the_user->save();

        $the_user_online->balance = $the_user->balance; 
        $the_user_online->start_time = date("Y-m-d H:i", $the_user_online->start_time);
        $the_user_online->end_time = date("Y-m-d H:i", $the_user_online->end_time);

        $this->assign('uo',$the_user_online);

        return $this->fetch();
    }

    public function recharge() {
            $card = input('post.card');
            $amount = input('post.amount');

            $the_user = model('Card')->get(['card' => $card]);

            $the_user->balance = $the_user->balance + $amount;

            $the_user->save(); 

            return $this->success("您已成功充值".$amount."元");
    }

    public function refund() {
        $data = [];
        $data = input('post.');

        $user = model('Card')->where('card', '=', $data['card'])->where('status', '=', '1')->select();
        
        if (count($user)==0) {
            return $this->error("用户不能存在或已经注销");
        }

        $user = $user[0];
        if ($user->password != $data['password']) {
            return $this->error('密码错误');
        }

        $u = [];
        $u['card'] = $user->card;
        $u['balance'] = $user->balance;
        $this->assign('u', $u);

        $user->balance =  0;
        $user->save();

        return $this->fetch();
    }

    public function cancel() {
        $data = [];
        $data = input('post.');

        $user = model('Card')->where('card', '=', $data['card'])->where('status', '=', '1')->select();
        
        if (count($user)==0) {
            return $this->error("用户不能存在或已经注销");
        }

        $user = $user[0];
        if ($user->password != $data['password']) {
            return $this->error('密码错误');
        }

        $user->status = -1;
        $user->save();
        return $this->success('注销成功');
    }

    public function bill() {
        $data = input('get.');


        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time']   = strtotime($data['end_time']);
        $bills = model('CardOnline')->where('start_time', 'egt', $data['start_time'])->where('start_time', 'elt', $data['end_time'])->where('status', '=', '-1');
        if (!empty($data['card'])) {
            $bills = $bills->where('card', '=', $data['card']);
        }

        $bills = $bills->select();

        return view('index/bill', ['cards' => $bills]);
    }
}
