<?php
namespace App\Module\Main\Controller;
use App\Model\Circle;
use App\Model\Activity;
use App\Model\ActivityApply;
use App\Model\ActivityComment;
use App\Model\ActivityAttachment;
use App\Model\ActivityCategory;
use App\Model\District;
use App\Model\Tag;
use App\Model\Member;
use App\Model\MemberProfile;
use App\Other\Utils;
use App\Helper\Cookie;
use App\Model\Ad;
use App\Model\AdSpace;

class ActivityController extends CommonController
{
    public function indexAction()
    {
        $page  = (int) $this->_request->getParam('page');
        $psize = $this->_request->getParam('psize');
        $cid = (int) $this->_request->getParam('cid');
        $page < 1 && $page = 1;
        if (is_numeric($psize)) {
        	$psize = (int) $psize;
        	$psize < 5 && $psize = 5;
        }

		// pc布局
		if ($this->clientVer != 'mobile') {
			$psize = 12;
		}

        $select = Activity::select()
			->where('is_del = 0')
			->where('status = 1')
			->order('publishd DESC')
			->order('activity_id DESC');

        if ($psize) {
        	$select->limitPage($page, $psize);
        }

        if ($cid) {
			$select1 = ActivityCategory::select()
				->where("parent_id = ". $cid);
			$cids = $select1->fetchColumn('cid');
			array_push($cids, $cid) ;
        	$select->where("cid in (?)", $cids);
        }

        $this->activitys = $select->find();
        $this->total = $select->find()->total();
        $this->has_more = 1;
		if($this->total < $psize){
			$this->has_more = 0;
		}

        $select = ActivityCategory::select()
            ->where('parent_id = 0')
            ->order('cid ASC')
            ->limit(5);

        $this->categorys = $select->find();
        $this->cid = $cid;

		$ajax = $this->_request->getParam('ajax');

		// pc
		if ($this->clientVer != 'mobile' && $ajax != 'json') {

			// tab选项卡分类内容
			foreach($this->categorys as $category){
				$cid = $category->cid;
				$select = Activity::select()
					->where('is_del = 0')
					->where('status = 1')
					->order('publishd DESC')
					->order('activity_id DESC');

				if ($psize) {
					$select->limitPage($page, $psize);
				}

				if ($cid) {
					$select1 = ActivityCategory::select()
						->where("parent_id = ". $cid);
					$cids = $select1->fetchColumn('cid');
					array_push($cids, $cid) ;
					$select->where("cid in (?)", $cids);
				}

				$std = 'activitys'.$cid ;
				$this->$std = $select->find();

				$this->total = $select->find()->total();
				$sname = 'has_more'.$cid ;
				$this->$sname = 1;
				if($this->total < $psize){
					$this->$sname = 0;
				}

			}

			//广告
			$select = Ad::select()
				->where('space_id = 23549527')
				->order('listorder DESC')
				->order('created DESC')
				->limit(5);
			$this->ads = $select->find();

		}

        $activitys = array();
        $i = 0;
        if ($ajax == 'json') {
            foreach ($this->activitys as $activity) {
                $activitys[$i] = $activity->props(true);
                $activitys[$i]['url'] = $activity->url();
                $activitys[$i]['logo'] = $activity->logo();
                $activitys[$i]['title'] = $activity->title(36);
                $activitys[$i]['city'] = str_replace('市','',$activity->city);
                $activitys[$i]['stime'] = $activity->stime('m/d');
                $activitys[$i]['apply_num'] = $activity->apply_num();
				//pc新增
                $activitys[$i]['summary'] = $activity->summary(78);
                $activitys[$i]['status'] = $activity->status();
				$activitys[$i]['pc_stime'] = $activity->stime('m月d日');
                $i++;
            }
            return $this->_json($activitys);
        }

        $this->query_args = array(
        );

        if (!$psize) {
        	return;
        }

        $this->paginator = new \App\Helper\Paginator;
        $this->paginator->setRecords($this->activitys->total())
             ->setPageSize($psize)
             ->setPage($page)
             ->setHttpQuery($this->query_args)
             ->init();
    }

    public function detailAction()
    {
		$this->is_mobile_auth = 0;
		if ($this->_identity) {
			$owner = Member::find($this->_identity->user_id);
			$this->is_mobile_auth = $owner->is_mobile_auth;
		}
		
    	$id = (int) $this->_request->getParam('id');
    	$activity = Activity::find($id);
    	if ($activity->isNil()) {
    		return $this->_forward('not-found', 'error', 'main');
    	}

    	$this->activity = $activity;

        $page              = (int) $this->_request->getParam('page');
        $page < 1 && $page = 1;
        $psize             = 100;

		$this->is_exp = 0 ;
		if($this->activity->enroll_deadline <= time()){
			$this->is_exp = 1 ;
		}

		// 报名用户
        $select = ActivityApply::select()
				->where('activity_id = '. $id)
				->where("is_del = 0")
				->where("status = 1")
                ->limitPage($page, $psize)
                ->order('created DESC')
                ->order('id DESC');
		$this->applys = $select->find();

		// 是否报名用户
		$this->is_apply = 0;
		if ($this->_identity) {
			$select = ActivityApply::select()
					->where('activity_id = '. $id)
					->where('user_id = '. $this->_identity->user_id)
					->where("is_del = 0");
					//->where("status = 1");
			
			$result = $select->find()->first();
			if (!$result->isNil()) {
				$this->is_apply = 1;
			}
		}

        // 是否填写真实姓名和公司名称
        if($activity->is_complete_info){
            $this->is_com = 0 ;
            $owner = Member::find($this->_identity->user_id);
            if($owner->realname && $owner->coname){
                $this->is_com = 1 ;
            }
        }else{
            $this->is_com = 1 ;
        }

        $activity_hits_key = 'key_activity_' . $this->activity->activity_id;
        $activity_hits_value = 'YES';
        $activity_hits = $_COOKIE[$activity_hits_key];
        if(!$activity_hits) {
        	setcookie($activity_hits_key, $activity_hits_value, (time()+3600*12));
        	$this->activity->updateHits();
        }
    }

    public function applyAction()
    {
    	if (!$this->_identity) {
            return $this->_json(array(
                'code' => -10,
                'message' => '您尚未登陆或登陆超时',
            ));
    	}
		
		$owner = Member::find($this->_identity->user_id);

    	$id = (int) $this->_request->getParam('id');
    	$activity = Activity::find($id);
    	if ($activity->isNil()) {
    		return $this->_forward('not-found', 'error', 'main');
    	}

		$backurl = urlencode($activity->url());
        if (!$owner->is_mobile_auth) {
            return $this->_json(array(
                'code' => -10,
                'message' => '请先绑定手机号码',
                'redirecturl' => '/member/bind-mobile?backurl=' . $backurl
            ));
        }

		$profile = MemberProfile::find($this->_identity->user_id);
        if (empty($profile->coname) || empty($profile->position)) {
            return $this->_json(array(
                'code' => -10,
                'message' => '请先完善资料，填写公司名称和职位',
                'redirecturl' => '/user/info?backurl=' . $backurl
            ));
        }

        if($activity->is_del == 1 ){
            return $this->_json(array(
                'code' => -10,
                'message' => '活动已删除',
            ));
        }

        if($activity->status != 1 ){
            return $this->_json(array(
                'code' => -10,
                'message' => '活动已结束',
            ));
        }

        /*
        if($activity->stime > time() ){
            return $this->_json(array(
                'code' => -10,
                'message' => '活动尚未开始',
            ));
        }
        */

        if($activity->enroll_deadline <= time() ){
            return $this->_json(array(
                'code' => -10,
                'message' => '活动报名已结束',
            ));
        }

        if($activity->apply_num() >= $activity->limit ){
            return $this->_json(array(
                'code' => -10,
                'message' => '活动报名人数已满',
            ));
        }

        $select = ActivityApply::select()
            ->where('is_del = 0')
            ->where('status < 3')
            ->where('activity_id = ' . $id)
            ->where('user_id = ' . $this->_identity->user_id)
            ->limit(1);

        $apply = $select->find()->first();
        if (!$apply->isNil()) {
            return $this->_json(array(
                'code' => -10,
                'message' => '您已经报名了',
            ));
        }

		$apply = new ActivityApply() ;
    	$apply->user_id = $this->_identity->user_id ;
    	$apply->status = 0;
    	$apply->activity_id = $id ;
    	$apply->save();

		return $this->_json(array(
			'code' => 1,
			'logo' => $apply->user->logo(),
			'uname' => $apply->user->dname,
			'created' => $apply->created(),
			'message' => '你已提交报名，请等待管理员审核'
		));

		//return $this->_jump('你已提交报名，请等待管理员审核', true, $_SERVER['HTTP_REFERER']);

	}
	
	public function signQrcodeAction()
    {
        $ajax = $this->_request->getParam('ajax');
        $id   = (int) $this->_request->getParam('id');
        $activity = Activity::find($id);
        if ($activity->isNil()) {
            return $this->_jump('活动不存在', false);
        }
		
        $pic = $activity->signQrcode('m');
        if ($ajax == 'json') {
            return $this->_json(array(
                'code' => 1,
                'pic' => $pic
            ));
        }

        header("Content-type: image/png");
        readfile($activity->signQrcodeFile('m'));
        exit;
    }
	
	public function signAction()
	{
		if ($this->clientVer != 'mobile') {
			return $this->_jump('请用手机微信扫一扫', false);
		}
		
		$id = (int) $this->_request->getParam('id');
		$sign = $this->_request->getParam('sign');
        $activity = Activity::find($id);
        if ($activity->isNil()) {
            return $this->_jump('活动不存在', false);
        }
		
		if (!$sign) {
			return $this->_jump('参数错误', false);
		}
		
		$signx = md5($activity->id . $activity->created);
		if ($sign != $signx) {
			return $this->_jump('签名字符串参数错误', false);
		}
		
		if ($activity->is_del) {
			return $this->_jump('活动已删除了哦。', false);
        }
		
		if ($activity->status <= 0) {
            return $this->_jump('该活动管理员尚未审核通过哦。', false);
        }

        if ($activity->status != 1) {
            return $this->_jump('活动已结束了哦。', false);
        }
		
		if ($activity->stime - time() > 3600*24) {
			return $this->_jump('活动尚未开始哦。', false);
		}
		
		$this->enrolled = false;
		if ($this->_identity) {
			$select = ActivityApply::select()
				->where('user_id = ' . $this->_identity->user_id)
				->where('activity_id = ' . $activity->id)
				->limit(1);
			
			$apply = $select->find()->first();
			if (!$apply->isNil()) {
				$this->enrolled = true;
			}
		}
		
		if ($this->_request->getMethod() == 'GET') {
			$this->activity = $activity;
			$this->sign = $sign;
			return;
		}
		
		$post = $this->_request->getPost();
		if ($this->_identity) {	
			$member = Member::find($this->_identity->user_id);
		
		} else {
			$rules = array (
				'mobile' => array (
					'label' => '手机号',
					array('not_empty')
				),
				'code' => array (
					'label' => '验证码',
					array('not_empty')
				)
			);
			
			$post = $this->_request->getPost();
			$post = Utils::trim($post);

			$validator = new \Yoo\Base\Validator($post);
			$validator->setRules($rules);
			$validator->validate();
			if ($validator->hasErrors()) {
				$msg = array();
				foreach ($validator->getErrors() as $errors) {
					foreach ($errors as $error) {
						$msg[] = $error;
					}
				}

				return $this->_jump($msg, false);
			}

			if (!preg_match('%^1[3-9][0-9]{9}$%', $post['mobile'])) {
				return $this->_jump('手机号码格式不正确', false);
			}

			$session = new \Yoo\Session\SessionNamespace('mobileauth');
			if (!$session->regcode) {
				return $this->_jump('验证码无效', false);
			}

			if ($session->regcode != $post['code']) {
				return $this->_jump('手机验证码错误', false);
			}

			if ($session->mobile != $post['mobile']) {
				return $this->_jump('您填写的手机号已更换，须重新获取验证码', false);
			}

			$select = Member::select()
				->where('mobile = ?', $post['mobile'])
				->limit(1);

			$member = $select->find()->first();
			if ($member->isNil()) {
				return $this->_jump('您尚未注册，无法报名', false);
			}
		}
		
		$select = ActivityApply::select()
			->where('user_id = ' . $member->id)
			->where('activity_id = ' . $activity->id)
			->limit(1);
		
		$apply = $select->find()->first();
		if ($apply->isNil()) {
			return $this->_jump('您未报名哦，请先报名。', false);
		}
		
		if ($apply->is_signed) {
			return $this->_jump('呵呵，您已经签过到了。', false);
		}
		
		if ($apply->status != 1) {
			return $this->_jump('很抱歉，您的报名未通过验证。', false);
		}
		
		$apply->sign();
		
		$url = strpos($activity->signjump, 'http') === 0 ? $activity->signjump : $activity->url();
		$this->_wxnotify($apply->user_id,
                'activity_sign_success',
                $url,
                array(
                    'head'  => "签到成功！",
                    'activity' => $activity->title,
                    'time'  => date('Y年m月d日 H:i'),
                    'place' => $activity->address,
                    'note'  => $activity->signreply
				)
		);
				
		$text = $activity->signreply ? $activity->signreply : '恭喜您，签到成功！';
		return $this->_jump($text, true, "/activity/sign-success?id={$activity->id}&sign={$sign}&url={$url}");
	}
	
	public function signSuccessAction()
	{
		$id = (int) $this->_request->getParam('id');
		$sign = $this->_request->getParam('sign');
		$url = $this->_request->getParam('url');
        $activity = Activity::find($id);
        if ($activity->isNil()) {
            return $this->_jump('活动不存在', false);
        }
		
		if (!$sign) {
			return $this->_jump('参数错误', false);
		}
		
		$signx = md5($activity->id . $activity->created);
		if ($sign != $signx) {
			return $this->_jump('签名字符串参数错误', false);
		}
		$text = $activity->signreply ? $activity->signreply : '恭喜您，签到成功！';
		return $this->_jump($text, true, $url, '', 20);
	}

}