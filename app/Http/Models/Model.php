<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/13
 * Time: 16:27
 */
namespace App\Http\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Model extends \Illuminate\Database\Eloquent\Model
{
    use SoftDeletes;
    protected $dateFormat = 'U';
    protected $dates   = ['delete_time'];
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';
    const DELETED_AT = 'delete_time';
    protected $fillable = ['*'];

    public function getDates(){
        return ['created_at','update_at','delete_at'];
    }

    public function setCreateTimeAttribute ($value) {
        $this->attributes['create_time'] = strtotime($value);
    }

    public function setUpdateTimeAttribute ($value) {
        $this->attributes['update_time'] = strtotime($value);
    }

    public function setDeleteTimeAttribute ($value) {
        $this->attributes['delete_time'] = strtotime($value);
    }

}