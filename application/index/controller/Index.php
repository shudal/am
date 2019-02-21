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

        $status = 1;
        if ( $the_user ) {
            if ($the_user->status == -1) {
                return $this->error('用户已注销');
            } else {
                if (model('CardOnline')->get(['card' => $card])) {
                    return $this->error('用户已上机');
                }
            }
        } else {
            return $this->error('用户名不存在或密码错误');
        }

        $newUp['card'] = $card;    
        $newUp['start_time']  = strtotime('now');

        model('CardOnline')->save($newUp);

        return $this->success('上机成功');
    }

    public function down() {
        $the_user = model('Card')->get(['card' => input('post.card'), 'password' => input('post.password')]);

        if (! $the_user) {
            return $this->error('用户名或密码错误');
        }

        $the_user_online = model('CardOnline')->get(['card' => input('post.card')]);

        if (! $the_user_online) {
            return $this->error('用户为上机');
        }

        $end_time = strtotime('now');
        $the_user_online->end_time = $end_time;

        $total_time = $end_time - $the_user_online->start_time;
        $total_time = $total_time  / (60 * 60);

        $money_use = 5 * $total_time;
        $the_user_online->money_use = $money_use; 

        $the_user_online->save();

    }
}
