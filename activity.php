<?php
namespace App\Model;
use App\Model\ActivityApply;
require_once APPLICATION_PATH . '/phpqrcode/phpqrcode.php';

class Activity extends CommonInfo
{

    protected static $_tablename = 'activity';
    protected static $_primary = array('activity_id');
    protected static $_useGlobalId = true;

    protected static $_has_one = array
    (
        'profile' => array(
            'refclass' => 'ActivityProfile',
            'onDelete' => 'cascade'
        ),
        'stat' => array(
            'refclass' => 'ActivityStat',
            'onDelete' => 'cascade'
        ),
    );

    protected static $_belongs_to = array
    (
        'photo' => array
        (//推荐图片
            'refclass' => 'ActivityPic',
            'pricols' => array('photo_id'),
            'refcols' => array('pic_id')
        ),

        'reviewphoto' => array
        (//回顾图片
            'refclass' => 'ActivtyPic',
            'pricols' => array('review_photo_id'),
            'refcols' => array('pic_id')
        ),

		'district' => array
		(
			'refclass' => 'District',
			'pricols' => array('distcode'),
			'refcols' => array('code')
		)
    );

    protected static $title__ = array
    (
        'colname' => 'title',
        'type' => 'string',
        'length' => array(3, 90),
    );

    protected static $_feetypes = array
    (
        0 => '免费',
        1 => 'AA制',
        2 => '收费',
        3 => '非会员收费'  //会员：免费；非会员：收费
    );

    public function apply_num()
    {
        $select = ActivityApply::select()
				->where("is_del = 0")
				->where("status = 1")
            ->where('activity_id = '. $this->activity_id);
        return $select->find()->total();
    }

    public function apply_nums()
    {
        $select = ActivityApply::select()
				->where("is_del = 0")
				->where("status <= 1")
            ->where('activity_id = '. $this->activity_id);
        return $select->find()->total();
    }

	public function getProv()
	{
		if (!$this->district->isNil()) {
			return $this->district->prov();
		}

		return '';
	}

	public function getCity()
	{
		if (!$this->district->isNil()) {
			return $this->district->city();
		}

		return '';
	}

	public function getDist()
	{
		if (!$this->district->isNil()) {
			return $this->district->dist();
		}

		return '';
	}

	public function getPcity()
	{
		if (!$this->district->isNil()) {
			return $this->district->pcity();
		}

		return '';
	}

    public function url()
    {
        if (!$this->id) {
            return '';
        }

        $action = 'detail';
        $query  = 'id/' . $this->id;
        $urlHelp = new \App\Helper\View\Url();
        $url = $urlHelp->direct(array(
            'module'     => 'main', 
            'controller' => 'activity',
            'action'     => $action,
            'query'      => $query
        ));
        
        return static::fullurl($url);
    }

    public function logo($size = null)
    {
        if ($this->photo->isNil()) {
            return static::fullurl('/upfile/activity/default-logo-s.jpg');
        }
		null === $size && $size = 's';
        return $this->photo->thumb($size);
    }

    public function photo($size = null)
    {
        if ($this->photo->isNil()) {
            return static::fullurl('/upfile/activity/default-logo-s.jpg');
        }

        if (null === $size) {
            return $this->photo->url();
        }

        return $this->photo->thumb($size);
    }

    public function reviewphoto($size = null)
    {
        if ($this->reviewphoto->isNil()) {
            return static::fullurl('/upfile/activity/default-logo-s.jpg');
        }

        if (null === $size) {
            return $this->reviewphoto->url();
        }

        return $this->reviewphoto->thumb($size);
    }

    public function stime($format = 'Y-m-d H:i')
    {
        return date($format, $this->stime);
    }

    public function etime($format = 'Y-m-d H:i')
    {
        return date($format, $this->etime);
    }

    public function week($chinese = true)
    {
        static $ar = array('一', '二', '三', '四', '五', '六', '日');
        $n = date('N', $this->stime);
        return isset($ar[$n - 1]) ? $ar[$n - 1] : '';
    }

    public function persons()
    {
        return $this->persons ? $this->persons . '人' : '不限';
    }

    public function status()
	{
		$status = $this->getRealStatus();
        switch ($status) {
            case -2:
                return '未通过';
            case -1:
                return '已取消';
            case 0:
                return '待审核';
			case 1:
				return '未开始';
            case 2:
                return '进行中';
            case 3:
                return '已结束';
            default:
                return '未知';
        }
	}
	
	public function getGoing()
    {
        return $this->status();
    }
	
	public function getRealStatus()
	{//活动状态
		if ($this->status == 0 || $this->status == -1 || $this->status == -2) {
			return $this->status;
		}
		
		if ($this->status == 3 || $this->status == 8) {
			return 3;
		}

		$time = time();
		if ($this->etime <= $time) {
			return 3;
		}

        if ($this->stime < $time) {
			return 2;
		}
		
		return 1;
	}

    public function cancel()
    {
        $this->status = -1;
        $this->save();
    }

    public function publishd($format = 'Y-m-d H:i:s')
    {
        return date($format, $this->publishd);
    }

    public function getFeeType()
    {
        $type = $this->prop('fee_type');
        if (isset(self::$_feetypes[$type])) {
            return self::$_feetypes[$type];
        }

        return '未知';
    }

    public function title($len, $charset = 'utf-8')
    {
        return mb_strimwidth(self::stripTags($this->title), 0, $len, '...', $charset);
    }

    public function subtitle()
    {
        if ($this->subtitle != '') {
            return $this->subtitle;
        } else {
            return $this->title;
        }
    }

    public function summary($len, $charset = 'utf-8')
    {
        return mb_strimwidth(self::stripTags($this->summary), 0, $len, '...', $charset);
    }
	
	public function signQrcodeFile($size = 'm')
    {//签到二维码图片生成，返回文件路径
        if ($this->activity_id <= 0) {
            throw new \Exception('The activity id must be larger than 0');
        }

        $url = $this->signUrl();
		
		$aid = sprintf("%09d", $this->id);
        $baseurl = substr($aid, 0, 3) . '/' . substr($aid, 3, 2) . '/' . substr($aid, 5, 2);

        $dir = dirname(APPLICATION_PATH) . '/upfile/activity/sign/' . $baseurl;
        $filename = $this->id . '_' . $size . '.png';
        $file =  $dir . '/' . $filename;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        \QRcode::png($url, $file, QR_ECLEVEL_Q, 6.25, 4, false, 0xffffff, 0x000000);
        return $file;
    }
	
	public function signQrcode($size = 'm')
    {//签到二维码图片URL
        if ($this->id <= 0) {
            throw new \Exception('The activity id must be larger than 0');
        }

        $this->signQrcodeFile($size);
		$aid = sprintf("%09d", $this->id);
        $baseurl = substr($aid, 0, 3) . '/' . substr($aid, 3, 2) . '/' . substr($aid, 5, 2);
        $picurl = '/upfile/activity/sign/' . $baseurl . '/' . $this->id . '_' . $size . '.png';
        $picurl = static::fullurl($picurl);
        return $picurl;
    }

	
	public function signUrl()
    {//签到URL
        if (!$this->id) {
            return '';
        }
		
        $expire  = strtotime('today') + (3 * 30 * 86400);
        $sign = md5($this->id . $this->created);

        $urlHelp = new \App\Helper\View\Url();
        $url = $urlHelp->direct(array(
            'module'     => 'main', 
            'controller' => '',
            'action'     => 'sign',
            'query'      => ''
        ));
		
		$url .= '?id=' . $this->id . '&sign=' . $sign;
        return static::fullurl($url);
    }
	
	public function signQrcodeUrl()
    {//签到二维码URL(注意与签到二维码图片URL区别)
        if (!$this->id) {
            return '';
        }
		
		 $sign = md5($this->id . $this->created);
		
        $urlHelp = new \App\Helper\View\Url();
        $url = $urlHelp->direct(array(
            'module'     => 'main', 
            'controller' => 'activity',
            'action'     => 'sign-qrcode',
            'query'      => ''
        ));

        $url .= '?id=' . $this->id . '&sign=' . $sign;
        
        return static::fullurl($url);
    }

    public function &__get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return parent::__get($name);
        }

        if ($this->getMeta()->hasPropertyDescription($name)) {
            return parent::__get($name);
        }

        $var = null;
        if (in_array($name, array('hits', 'yshits', 'thits', 'whits', 'mhits', 'weeknum', 'day'))) {
            if ($this->stat->isNil()) {
                $var = 0;
            } else {
                $var = & $this->stat->__get($name);
            }
        } else {
            $var = & $this->profile->__get($name);
        }
        return $var;
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->profile, $method), $args);
    }

}
