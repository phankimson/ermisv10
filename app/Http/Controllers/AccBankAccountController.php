<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Model\AccHistoryAction;
use App\Http\Model\User;
use App\Http\Model\Menu;
use App\Http\Model\AccBankAccount;
use App\Http\Model\AccBank;
use App\Http\Model\CompanySoftware;
use App\Http\Model\Company;
use App\Http\Model\Error;
use App\Http\Resources\DropDownListResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Model\Imports\AccBankAccountImport;
use App\Http\Model\Exports\AccBankAccountExport;
use App\Classes\Convert;
use Illuminate\Support\Str;
use Excel;

class AccBankAccountController extends Controller
{
  public function __construct(Request $request)
  {
     $this->url =  $request->segment(3);
     $this->key = "bank-account";
     $this->menu = Menu::where('code', '=', $this->key)->first();
 }

  public function show(Request $request){
    $mysql2 = $request->session()->get('mysql2');
    config(['database.connections.mysql2' => $mysql2]);
    $data = AccBankAccount::get_raw();
    $bank = collect(DropDownListResource::collection(AccBank::active()->OrderBy('code','asc')->get()));
    return view('acc.bank_account',['data' => $data, 'key' => $this->key ,'bank'=>$bank ]);
  }

  public function ChangeDatabase(Request $request){
    $type = 9;
    try{
      $req = json_decode($request->data);
      $db = CompanySoftware::find($req->database);
      $com = Company::find($db->company_id);
      $params = array(
            'driver'    => env('DB_CONNECTION', 'mysql'),
            'host'      => env('DB_HOST', '127.0.0.1'),
            'database'  => $db->database,
            'username'  => $db->username,
            'password'  => $db->password,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        );
      $request->session()->put('mysql2', $params);
      config(['database.connections.mysql2' => $params]);
      $data = AccBankAccount::get_raw();
      return response()->json(['status'=>true,'data'=> $data,'com_name'=> $com->name ]);
    }catch(Exception $e){
      // Lưu lỗi
      $err = new Error();
      $err ->create([
        'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
        'user_id' => Auth::id(),
        'menu_id' => $this->menu->id,
        'error' => $e->getMessage(),
        'url'  => $this->url,
        'check' => 0 ]);
      return response()->json(['status'=>false,'message'=> trans('messages.error').' '.$e->getMessage()]);
    }
 }

  public function save(Request $request){
    $mysql2 = $request->session()->get('mysql2');
    config(['database.connections.mysql2' => $mysql2]);
    $type = 0;
    try{
  $permission = $request->session()->get('per');
  $arr = json_decode($request->data);
  $validator = Validator::make(collect($arr)->toArray(),[
            'bank_account' => ['required','max:50'],
            'bank_name' => 'required',
        ]);
     if($validator->passes()){
      $code_check = AccBankAccount::WhereCheck('bank_account',$arr->bank_account,'id',$arr->id)->first();
      if($code_check == null){
     if($permission['a'] == true && !$arr->id ){
       $type = 2;
       $data = new AccBankAccount();
       $data->bank_id = $arr->bank_id;
       $data->bank_account = $arr->bank_account;
       $data->bank_name = $arr->bank_name;
       $data->branch = $arr->branch;
       $data->description = $arr->description;
       $data->active = $arr->active;
       $data->save();

       // Lưu lịch sử
       $h = new AccHistoryAction();
       $h ->create([
         'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
         'user' => Auth::id(),
         'menu' => $this->menu->id,
         'url'  => $this->url,
         'dataz' => \json_encode($data)]);

       // Lấy ID và và phân loại Thêm
       $arr->id = $data->id;
       $arr->t = $type;
       broadcast(new \App\Events\DataSend($arr));
       return response()->json(['status'=>true,'message'=> trans('messages.update_success')]);
     }else if($permission['e'] == true && $arr->id){
       $type = 3;
       $data = AccBankAccount::find($arr->id);
       // Lưu lịch sử
       $h = new AccHistoryAction();
       $h ->create([
         'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
         'user' => Auth::id(),
         'menu' => $this->menu->id,
         'url'  => $this->url,
         'dataz' => \json_encode($data)]);
      //

      $data->bank_id = $arr->bank_id;
      $data->bank_account = $arr->bank_account;
      $data->bank_name = $arr->bank_name;
      $data->branch = $arr->branch;
      $data->description = $arr->description;
      $data->active = $arr->active;
      $data->save();
       // Phân loại Sửa
       $arr->t = $type;
       broadcast(new \App\Events\DataSend($arr));
       return response()->json(['status'=>true,'message'=> trans('messages.update_success')]);
       }else{
        return response()->json(['status'=>false,'message'=> trans('messages.you_are_not_permission')]);
       }
      }else{
        return response()->json(['status'=>false,'message'=> trans('messages.code_is_already')]);
      }
     }else{
       return response()->json(['status'=>false,'error'=>$validator->getMessageBag()->toArray() ,'message'=>trans('messages.error')]);
     }
    }catch(Exception $e){
      // Lưu lỗi
      $err = new Error();
      $err ->create([
        'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
        'user_id' => Auth::id(),
        'menu_id' => $this->menu->id,
        'error' => $e->getMessage(),
        'url'  => $this->url,
        'check' => 0 ]);
      return response()->json(['status'=>false,'message'=> trans('messages.error').' '.$e->getMessage()]);
    }
 }

 public function delete(Request $request) {
   $mysql2 = $request->session()->get('mysql2');
   config(['database.connections.mysql2' => $mysql2]);
   $type = 4;
      try{
        $permission = $request->session()->get('per');
        $arr = json_decode($request->data);
        if($arr){
          if($permission['d'] == true){
            $data = AccBankAccount::find($arr->id);
            // Lưu lịch sử
            $h = new AccHistoryAction();
            $h ->create([
            'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
            'user' => Auth::id(),
            'menu' => $this->menu->id,
            'url'  => $this->url,
            'dataz' => \json_encode($data)]);
            //
            $data->delete();
            broadcast(new \App\Events\DataSend($arr));
            return response()->json(['status'=>true,'message'=> trans('messages.delete_success')]);
          }else{
            return response()->json(['status'=>false,'message'=> trans('messages.you_are_not_permission_delete')]);
          }
       }else{
         return response()->json(['status'=>false,'message'=> trans('messages.no_data_found')]);
       }
      }catch(Exception $e){
        // Lưu lỗi
        $err = new Error();
        $err ->create([
          'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
          'user_id' => Auth::id(),
          'menu_id' => $this->menu->id,
          'error' => $e->getMessage(),
          'url'  => $this->url,
          'check' => 0 ]);
        return response()->json(['status'=>false,'message'=> trans('messages.delete_fail').' '.$e->getMessage()]);
      }
 }

 public function DownloadExcel(Request $request){
   return Storage::download('public/downloadFile/AccBankAccount.xlsx');
 }

 public function import(Request $request) {
   ini_set('max_execution_time', 600);
   $mysql2 = $request->session()->get('mysql2');
   config(['database.connections.mysql2' => $mysql2]);
  $type = 5;
   try{
   $permission = $request->session()->get('per');
   if($permission['a'] && $request->hasFile('file')){
     //Check
     $request->validate([
         'file' => 'required|mimeTypes:'.
               'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
               'application/vnd.ms-excel',
     ]);
       $rs = json_decode($request->data);

       $file = $request->file;
       // Import dữ liệu
       Excel::import(new AccBankAccountImport, $file);
       // Lấy lại dữ liệu
       $array = AccBankAccount::get_raw();

       // Import dữ liệu bằng collection
       //$results = Excel::toCollection(new HistoryActionImport, $file);
       //dump($results);
       //foreach($results[0] as $item){
       //  $data = new HistoryAction();
       //  $data->type = $item->get('type');
       //  $data->user = $item->get('user');
       //  $data->menu = $item->get('menu');
       //  $data->dataz = $item->get('dataz');
       //  $data->save();
       //  $arr->push($data);
       //}
       $merged = collect($rs)->push($array);
       //dump($merged);
     // Lưu lịch sử
     $h = new AccHistoryAction();
     $h ->create([
       'type' => $type, // Add : 2 , Edit : 3 , Delete : 4, Import : 5
       'user' => Auth::id(),
       'menu' => $this->menu->id,
        'url'  => $this->url,
       'dataz' => \json_encode($merged)]);
     //
     //Storage::delete($savePath.$filename);
     broadcast(new \App\Events\DataSendCollection($merged));
     return response()->json(['status'=>true,'message'=> trans('messages.success_import')]);
     }else{
       return response()->json(['status'=>false,'message'=> trans('messages.no_data_found')]);
     }
   }catch(Exception $e){
     // Lưu lỗi
     $err = new Error();
     $err ->create([
       'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
       'user_id' => Auth::id(),
       'menu_id' => $this->menu->id,
       'error' => $e->getMessage(),
       'url'  => $this->url,
       'check' => 0 ]);
     return response()->json(['status'=>false,'message'=> trans('messages.failed_import').' '.$e->getMessage()]);
   }
 }

 public function export(Request $request) {
   $mysql2 = $request->session()->get('mysql2');
   config(['database.connections.mysql2' => $mysql2]);
   $type = 6;
   try{
       $arr = $request->data;
       //return (new HistoryActionExport($arr))->download('HistoryActionExportErmis.xlsx');
       //$myFile = Excel::download(new HistoryActionExport($arr), 'HistoryActionExportErmis.xlsx');
       $myFile = Excel::raw(new AccBankAccountExport($arr,$mysql2['database']), \Maatwebsite\Excel\Excel::XLSX);
       $response =  array(
         'status' =>true,
         'name' => "AccBankAccountExportErmis", //no extention needed
         'file' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,".base64_encode($myFile) //mime type of used format
      );
      return response()->json($response);
   }catch(Exception $e){
     // Lưu lỗi
     $err = new Error();
     $err ->create([
       'type' => $type, // Add : 2 , Edit : 3 , Delete : 4
       'user_id' => Auth::id(),
       'menu_id' => $this->menu->id,
       'error' => $e->getMessage(),
       'url'  => $this->url,
       'check' => 0 ]);
     return response()->json(['status'=>false,'message'=> trans('messages.failed_import').' '.$e->getMessage()]);
   }
 }

}
