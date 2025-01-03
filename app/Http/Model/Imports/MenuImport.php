<?php

namespace App\Http\Model\Imports;

use App\Http\Model\Menu;
use App\Http\Model\Software;
use Illuminate\Support\Str;
use App\Classes\Convert;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MenuImport implements ToModel, WithHeadingRow, WithBatchInserts, WithLimit , WithStartRow
{
  private static $result = array();
  public function sheets(): array
    {
        return [
            new FirstSheetImport()
        ];
    }
    public function setData($arr)
    {
        array_push(self::$result,$arr);
    } 

    public function getData()
    {
        return self::$result;
    }   


  /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null    */

    public function model(array $row)
    {
        $menu = Menu::WhereDefault('code',$row['parent'])->first();
        $type = Software::WhereDefault('url',$row['type'])->first();
        $code_check = Menu::WhereCheck1('code',$row['code'],'link',$row['link'],'id',null)->first();
        if($code_check == null){
          $arr = [
            'id'     => Str::uuid()->toString(),
            'type'    => $type == null ? 0 : $type->id,
            'parent_id'  => $menu == null ? 0 : $menu->id,
            'code'    => $row['code'],
            'name'    => $row['name'],
            'name_en'    => $row['name_en'],
            'icon'    => $row['icon'],
            'link'    => $row['link'],
            'position'    => Convert::IntDefaultformat($row['position']),
            'active'    => $row['active'] == null ? 1 : $row['active'],
          ];
          $data = new MenuImport();
          $data->setData($arr);
        return new Menu($arr);
     }
    }
   
    public function batchSize(): int
    {
      return env("IMPORT_SIZE",100);
    }   
  
     public function limit(): int
     {
      return env("IMPORT_LIMIT",200);
     }

     public function headingRow(): int
     {
         return env("HEADING_ROW",1);
     }
       public function startRow(): int
     {
         return env("START_ROW",2);
     }


}
