<?php
namespace App\Model; //这

class ActivityCategory extends Category
{
    protected static $_tablename = 'activity_category';
    protected static $_primary = array('cid');
    protected static $cid__ = array(
        'colname' => 'cid',
        'type' => 'smallint',
    );

    public function activitys($limit = 8)
    {
        $select = Activity::select()
            ->where('catcode LIKE ?', $this->code . '%')
            ->order('listorder')
            ->limit($limit);
        return $select->find();
    }

    public function getCode()
    {
        if ($code = $this->prop('code')) {
            return $code;
        }

        $codes = array();
        $codes[] = sprintf('%05d', $this->id);
        if ($this->parent_id > 0) {
            $codes[] = sprintf('%05d', $this->parent_id);
            $parent = static::find($this->parent_id);
            if ($parent->isNil()) {
                throw new \Exception('The system has error in product category');
            }

            if ($parent->parent_id > 0) {
                $codes[] = sprintf('%05d', $parent->parent_id);
            }
        }

        $codes = array_reverse($codes);
        $code = join('', $codes);
        $this->code = $code;
        $this->save();

        return $code;
    }

    public function __toString()
    {
        return $this->name;
    }
}
