<?php

namespace app\modules\controllers;

use Yii;
use yii\web\UploadedFile;
use app\helpers\Checksum;
use yii\base\Hcontroller;
use app\modules\models\TblUtility;
use app\modules\models\TblProvider;
use app\modules\models\TblProviderBillUploadDetails;
use app\modules\models\TblProviderInvoice;
use app\modules\models\TblProviderBillDetails;
use app\modules\models\TblInvoiceBillDetails;

class DefaultController extends HController
{
  public $enableCsrfValidation = false;
	public function actionIndex()
	{
		$data=Yii::$app->user->identity;
		// print_r($data);
		// exit;
		// if(!($data['USER_ID'])){
			//     // $this->redirect('/web');
			// }
			$utilities = TblUtility::find()->all();
			return $this->render('index',array('utilities'=>$utilities));
		}
		
		public function actionProviders(){
			$id=Yii::$app->request->post('utility_id');
			if($id){
				$providers = TblProvider::find()
        ->where(['utility_id' => $id ])
        ->andWhere(['is_disabled' => 'n'])
        ->all();
				$providers_list=array();
				$provider_data=array();
				foreach($providers as $key=>$value){
					$provider_data['id']=$value->provider_id;
					$provider_data['name']=$value->provider_name;
					$providers_list[]=$provider_data;
				}
				echo json_encode($providers_list);
			} else {
				echo "not found";
			}
		}
		
		public function actionPaying(){
			if($_FILES['bulk_upload']['tmp_name']){
				$uploadedFile_data = $this->upload();
				if($uploadedFile_data){
          $invoice_id = $this->invoice_create();
          $bill_details=array();
          $data=array();
          $handle = fopen( Yii::$app->getBasePath()."/modules/resources/upload/".$uploadedFile_data['file_name'], "r");
          fgetcsv($handle);
          while (($fileop = fgetcsv($handle, 1024, ",")) !== false) 
          {
            $data['account_id']= $fileop[3];
            $data['fname'] = $fileop[0];
            $data['lname'] = $fileop[1];
            $data['email']= $fileop[2];
            $data['mobile'] = $fileop[3];
            $bill_details[]=$data;
            $this->bill_details($uploadedFile_data,$invoice_id,$data);
          }
          return $this->render('data_uploaded',array('invoice_id'=>$invoice_id));
        } else{
          echo "Error while uploading file";
        }
      } else {
        $invoice_id = $this->invoice_create();
        $bill_details['account_id']=Yii::$app->request->post('mobile_no');
        $bill_details['fname']=Yii::$app->request->post('fname');
        $bill_details['lname']=Yii::$app->request->post('lname');
        $bill_details['email']=Yii::$app->request->post('email');
        $bill_details['mobile']=Yii::$app->request->post('mobile_no');
        $this->bill_details(0,$invoice_id,$bill_details);
        return $this->render('loading',array('invoice_id'=>$invoice_id));
      }
      $api_data=[
        'Invoice_no'=>$invoice_id,
        'profile_id'=>"",
        'utitlity_id'=>Yii::$app->request->post('utility_name'),
        'provide_id'=>Yii::$app->request->post('providers'),
        'private_key'=>"",
        'retunr_url'=>'192.168.1.127/partnerpay/web/bbps/default/response',
        'checkSum'=>"",
        'bill_data'=>$bill_details,
      ];
      // echo "<pre>";
      // print_r(json_encode($api_data));
      // $curl = curl_init('http://localhost/partnerpay/web/bbps/default/response');
      // curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
      // curl_setopt($curl, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
      // $curl_response = curl_exec($curl);
      // curl_close($curl); 
      // print_r($output);
      // exit;
      return $this->render('data_uploaded',array('invoice_id'=>$invoice_id));
    }
    
    
    public function upload(){
      $uploadOk = 1;
      $target_dir = Yii::$app->getBasePath()."/modules/resources/upload/";
      $ext = pathinfo($_FILES["bulk_upload"]["name"], PATHINFO_EXTENSION);
      if($ext != "csv"){
        $uploadOk=0;
      }
      $new_name = time().'_'.Yii::$app->request->post('providers').'_'.Yii::$app->request->post('utility_name').'.'.$ext;
      $target_file = $target_dir.$new_name;
      $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
      if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
      } else {
        if (move_uploaded_file($_FILES["bulk_upload"]["tmp_name"], $target_file)) {
          
          $model = new TblProviderBillUploadDetails();
          $model->XLS_NAME=$new_name;
          $model->MODIFIED_DATE=date("Y-m-d");
          if($model->save()){
            $data = array();
            $data['file_name']=$new_name;
            $data['inserted_id']=$model->getPrimaryKey();
            return $data;
          } else{
            return false;
          }
        } else {
          return false;
        }
      }
      
    }
    
    public function invoice_create(){
      $model = new TblProviderInvoice();
      $model->STATUS="pending";
      $model->MODIFIED_DATE=date("Y-m-d");
      if($model->save()){
        $invoice_id=$model->getPrimaryKey();
        return $invoice_id;
      }
    }
    
    public function bill_details($uploadedFile_data,$invoice_id,$data){
      $model= new TblProviderBillDetails();
      $model->PROVIDER_ID=Yii::$app->request->post('providers');
      $model->UTILITY_ID=Yii::$app->request->post('utility_name');
      if($uploadedFile_data['inserted_id']){
        $model->PROVIDER_BILL_UPLOAD_DETAILS_ID=$uploadedFile_data['inserted_id'];
      }
      $model->FNAME=$data['fname'];
      $model->LNAME=$data['lname'];
      $model->EMAIL=$data['email'];
      $model->MOBILE_NO=$data['mobile'];
      $data=Yii::$app->user->identity;
      $model->USER_ID= $data['USER_ID'];
      $model->INVOICE_ID=$invoice_id;
      $model->PAYMENT_STATUS="pending";
      if(Yii::$app->request->post('register')){
        $model->IS_REGISTER='y';
      } else {
        $model->IS_REGISTER='n';
      }
      if($model->save(false)){
        $billing_details_id=$model->getPrimaryKey();
        $invoice_bill= new TblInvoiceBillDetails();
        $invoice_bill->INVOICE_ID=$invoice_id;
        $invoice_bill->PROVIDER_BILL_DETAILS_ID=$billing_details_id;
        $invoice_bill->INVOICE_GENERATED_DATE=date("Y-m-d");
        $invoice_bill->PAYMENT_STATUS="pending";
        $invoice_bill->MODIFIED_DATE=date("Y-m-d");
        $invoice_bill->save();
      }
      
    }
    
    public function actionResponse(){
      // $data=[
      //   "Invoice_no"=> 59,
      //   "private_key"=> "asdasdasdasd",
      //   "checksum"=> "asdassdasdasdasd",
      //   "BankResponse"=>[
      //     [
      //       "validationid"=> "12112114",
      //       "validation_date"=> "07-02-2017 22:06:34",
      //       "valid_until"=> "10-02-2017 22:06:34",
      //       "billnumber"=> "9869457154",
      //       "billdate"=> "02-08-2017",
      //       "billduedate"=> "02-09-2017",
      //       "billamount"=> "500.00",
      //       "early_billduedate"=> "28-08-2017",
      //       "early_billdiscount"=> "15.00",
      //       "early_billamount"=> "485.00",
      //       "late_payment_charges"=> "50.00",
      //       "late_payment_amount"=> "550.00",
      //       "net_billamount"=> "550.00"
      //     ],
      //     ]
      //   ];
        // $this->enableCsrfValidation = false;
        // echo "asdads";
        $post = Yii::$app->request->rawBody;
        print_r($post);
        if($post){
          return "true";
        } else {
          return 'gjkhjk';
        }
        // return true;
        // print_r(json_encode($data));
        /*$data1 = json_encode($data);
        $data2 = json_decode($data1);*/
        // $model= new TblProviderBillDetails();
        // foreach($data2->BankResponse as $value){
        //   $connection = Yii::$app->db;  
        //   $connection->createCommand()
        //   ->update('tbl_provider_bill_details', ['ISSUE_DATE'=>date('Y-m-d H:i:s',strtotime($value->validation_date)),'DUE_DATE'=>date('Y-m-d H:i:s',strtotime($value->billduedate)),'EARLY_DISCOUNT'=>$value->early_billdiscount,'LATE_FEE'=>$value->late_payment_charges,'EARLY_DUE_DATE'=>date('Y-m-d H:i:s',strtotime($value->early_billduedate)),'NET_AMOUNT'=>$value->net_billamount,'AMOUNT'=>$value->billamount,'REF_NO'=>$value->validationid,'RESPONSE_NOT_RECIEVED'=>0], 'MOBILE_NO='.$value->billnumber.' AND INVOICE_ID='.$data2->Invoice_no)
        //   ->execute();
          // echo "<br>";
        //   print_r($value->validationid);
        // }
      }
      
      public function actionListing($invoice_id){
        $data=Yii::$app->user->identity;
        $connection = Yii::$app->db;
        $all_invoice = $connection
        ->createCommand('Select SUM(b.NET_AMOUNT) as invoice_amount,SUM(b.RESPONSE_NOT_RECIEVED) as recieved,b.PROVIDER_ID,p.provider_name,b.PAYMENT_STATUS,b.INVOICE_ID,u.utility_name from tbl_provider_bill_details as b JOIN tbl_provider as p on b.PROVIDER_ID=p.provider_id JOIN tbl_utility as u on b.UTILITY_ID=u.utility_id where b.USER_ID=:userid AND b.REMOVED="n" GROUP BY INVOICE_ID Order By INVOICE_ID DESC');
        $all_invoice->bindValue(':userid', $data['USER_ID']);
        $all_invoice_data = $all_invoice->queryAll();
        $query="SELECT utility_id,utility_name from tbl_utility where is_disabled='n'";
        $utility = $connection->createCommand($query);
        $utility_data= $utility->queryAll();
        return  $this->render('listing',array('invoice_id'=>$invoice_id,'invoice_data'=>$all_invoice_data,'utility_data'=>$utility_data));
      }  
      
      public function actionChecking(){
        $connection = Yii::$app->db;
        $checkresponse = $connection
        ->createCommand("Select SUM(NET_AMOUNT) as invoice_amount,SUM(RESPONSE_NOT_RECIEVED) as recieved from  tbl_provider_bill_details where INVOICE_ID=:invoice_id");
        $checkresponse->bindValue(':invoice_id', Yii::$app->request->post('id'));
        $checkresponse_data = $checkresponse->queryAll();
        if($all_invoice_data[0]['recieved']==0){
          echo json_encode($all_invoice_data);
        } else {
          echo false;
        }
      }
      
      public function actionPayment($invoice_id){
        $connection = Yii::$app->db;
        $invoice = $connection
        ->createCommand("Select b.NET_AMOUNT,b.RESPONSE_NOT_RECIEVED,b.PROVIDER_ID,p.provider_name,b.ISSUE_DATE,b.INVOICE_ID,b.DUE_DATE,b.EARLY_DUE_DATE,b.EARLY_DISCOUNT,b.LATE_FEE,b.MOBILE_NO from tbl_provider_bill_details as b JOIN tbl_provider as p on b.PROVIDER_ID=p.provider_id where b.INVOICE_ID=:invoice_id AND b.REMOVED='n'");
        $invoice->bindValue(':invoice_id', $invoice_id);
        $invoice_data = $invoice->queryAll();
        $sum = $this->calculate_sum($invoice_data);
        return $this->render('payment',array('invoice_amount'=>$sum,'invoice_data'=>$invoice_data,'provider'=>$invoice_data[0]['provider_name']));
      }
      
      public function calculate_sum($data){
        $sum=0;
        foreach($data as $value){
          if(strtotime("now")>strtotime($value['DUE_DATE'])){
            $sum = $sum + $value['NET_AMOUNT'] + $value['LATE_FEE'];
          } else if(strtotime("now")<strtotime($value['EARLY_DUE_DATE'])) {
            $sum = $sum + $value['NET_AMOUNT'] - $value['EARLY_DISCOUNT'];
          }else{
            $sum = $sum + $value['NET_AMOUNT'];
          }
        }
        return $sum;
      }
      
      public function actionDeletemobile(){
        $connection = Yii::$app->db;
        $invoice_mobile_delete = $connection->createCommand()
        ->update('tbl_provider_bill_details', ['REMOVED' => 'y'], 'INVOICE_ID='.Yii::$app->request->post('invoice_id').' AND MOBILE_NO='.Yii::$app->request->post('mobile_no'))->execute();
        echo $invoice_data;
        if($invoice_mobile_delete){
          $invoice = $connection
          ->createCommand("Select b.NET_AMOUNT,b.RESPONSE_NOT_RECIEVED,b.PROVIDER_ID,p.provider_name,b.ISSUE_DATE,b.INVOICE_ID,b.DUE_DATE,b.EARLY_DUE_DATE,b.EARLY_DISCOUNT,b.LATE_FEE,b.MOBILE_NO from tbl_provider_bill_details as b JOIN tbl_provider as p on b.PROVIDER_ID=p.provider_id where b.INVOICE_ID=:invoice_id AND b.REMOVED='n'");
          $invoice->bindValue(':invoice_id', Yii::$app->request->post('invoice_id'));
          $invoice_data = $invoice->queryAll();
          $sum = $this->calculate_sum($invoice_data);
          echo json_encode(['sum'=>$sum]);
        } else {
          echo false;
        }
      }
      
      public function actionRemoved(){
        $data=Yii::$app->user->identity;
        $connection = Yii::$app->db;
        $query="SELECT b.NET_AMOUNT,b.PROVIDER_ID,p.provider_name,DATE_FORMAT(b.ISSUE_DATE,'%d/%m/%Y') as ISSUE_DATE,b.INVOICE_ID,DATE_FORMAT(b.DUE_DATE,'%d/%m/%Y')as DUE_DATE,b.EARLY_DUE_DATE,b.EARLY_DISCOUNT,b.LATE_FEE,b.MOBILE_NO from tbl_provider_bill_details as b JOIN tbl_provider as p on b.PROVIDER_ID=p.provider_id where b.UTILITY_ID=:utility_id AND b.REMOVED='y' AND b.PROVIDER_ID=:provider_id AND USER_ID=:user_id";
        $removed = $connection->createCommand($query);
        $removed->bindValue(':utility_id', Yii::$app->request->post('utility_id'));
        $removed->bindValue(':provider_id', Yii::$app->request->post('provider_id'));
        $removed->bindValue(':user_id', $data['USER_ID']);
        $removed_data= $removed->queryAll();
        echo json_encode($removed_data);
      }
      
      public function actionPay(){
        $data=Yii::$app->user->identity;
        $chk = new Checksum();
        $privatekey = $chk->encrypt($data['EMAIL'].":|:".$data['PASSWORD'], "12345");
        
        $checksum = $chk->calculateChecksum($data['MERCHANT_ID'].Yii::$app->request->post('invoice_no')."356.00".$data['EMAIL']."9869478152".$data['FIRST_NAME'].$data['LAST_NAME']."356"."INR".date('Y-m-d'),$privatekey);
        
        return $this->render('airpay_payment',array('payment_data'=>Yii::$app->request->post(),"key"=>$key,"checksum"=>$checksum));
      }
    }      
    