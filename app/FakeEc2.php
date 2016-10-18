<?php
//
//  FakeEc2 - EC2 シミュレーター
//
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support;
use RuntimeException;
use Illuminate\Database\Query\Builder;
use App\Jobs\ChangeStateJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\FakeEc2;

class FakeEc2 extends Model
{
  use DispatchesJobs;

  protected $table = 'fake_ec2';          //  実テーブル名
  protected $primaryKey = 'instance_id';  //  プライマリキー項目名
  public $incrementing = false;           //  主キーは自動増分?
  public $timestamps = false;             //  自動更新のタイムスタンプ項目あり

  /**
   * EC2 インスタンスの状態変更
   *
   * @param  string  $instance_id
   * @param  string  $state
   * @return void
   */
  public function changeState($instance_id, $state = 'unKnown')
  {
    $ec2 = $this->find($instance_id);
    if (!$ec2)  {
      throw new RuntimeException('インスタンスが未登録');
    }
    $ec2->attributes['state'] = $state;
    $ec2->save();
  }

  /**
   * クエリーに "order by" 句を追加する
   *
   * @param  string  $column
   * @param  string  $direction
   * @return Illuminate\Database\Query\Builder
   */
  public function orderBy($column, $direction = 'asc')
  {
    return parent::orderBy($column);
  }

  /**
   * インスタンスの起動
   *
   * @return void
   *
   * @throws RuntimeException
   */
  public function start($instance_id)
  {
    \Log::info(__CLASS__.'::'.__METHOD__.'('.$instance_id.') called.');
    $ec2 = $this->find($instance_id);
    if (!$ec2)  {
      throw new RuntimeException('インスタンスが未登録');
    }
    if ($ec2->attributes['state'] != 'stopped')  {
      throw new RuntimeException('インスタンスの状態が停止中以外');
    }
    $ec2->attributes['state'] = 'pending';             //  起動処理中へ
    $ec2->save();
    $this->dispatch(new ChangeStateJob($instance_id, 'running', 30));
  }

  /**
   * インスタンスの停止
   *
   * @return void
   *
   * @throws RuntimeException
   */
  public function stop($instance_id)
  {
    \Log::info(__CLASS__.'::'.__METHOD__.'('.$instance_id.') called.');
    $ec2 = $this->find($instance_id);
    if (!$ec2)  {
      throw new RuntimeException('インスタンスが未登録');
    }
    if ($ec2->attributes['state'] != 'running')  {
      throw new RuntimeException('インスタンスの状態が実行中以外');
    }
    $ec2->attributes['state'] = 'stopping';             //  停止処理中へ
    $ec2->save();
    $this->dispatch(new ChangeStateJob($instance_id, 'stopped', 30));
  }

  /**
   * インスタンスの再起動
   *
   * @return void
   *
   * @throws RuntimeException
   */
  public function reboot($instance_id)
  {
    \Log::info(__CLASS__.'::'.__METHOD__.'('.$instance_id.') called.');
    $ec2 = $this->find($instance_id);
    if (!$ec2)  {
      throw new RuntimeException('インスタンスが未登録');
    }
    if ($ec2->attributes['state'] == 'running') {
      $ec2->attributes['state'] = 'stopping';     //  停止処理中へ
    } else if ($ec2->attributes['state'] == 'stopped')  {
      $ec2->attributes['state'] = 'pending';      //  起動処理中へ
    } else {
      throw new RuntimeException('インスタンスの状態が実行中／停止済み以外');
    }
    $ec2->save();
    $this->dispatch(new ChangeStateJob($instance_id, 'running', 30));
  }

  /**
   * インスタンス一覧の取得
   *
   * @return array
   */
  public function setData()
  {
    $ec2 = $this->orderBy('nickname')->get();
    $list = [];
    foreach ($ec2 as $item) {
      $list[] = $item->attributes;
    }
    return $list;
  }

}
