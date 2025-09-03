<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=results_export.csv');

$filters = [
  'q'=>$_GET['q']??'', 'date_from'=>$_GET['date_from']??'',
  'date_to'=>$_GET['date_to']??'', 'status'=>$_GET['status']??'',
  'patient_id'=>isset($_GET['patient_id'])?(int)$_GET['patient_id']:0,
];
$params=[]; $where=build_where($filters,$params);

/* Build patient name in SQL: "Last, First M. Suffix" */
$sql="SELECT oi.id, o.id AS order_id, p.id AS patient_id,
             CONCAT(
               p.last_name,
               IF(p.last_name IS NOT NULL AND p.last_name <> '' AND p.first_name IS NOT NULL AND p.first_name <> '', ', ', ''),
               p.first_name,
               IF(p.middle_name IS NOT NULL AND p.middle_name <> '', CONCAT(' ', LEFT(p.middle_name,1),'.'), ''),
               IF(p.suffix IS NOT NULL AND p.suffix <> '', CONCAT(' ', p.suffix), '')
             ) AS patient,
             t.code, t.name AS test, t.department, o.ordering_doctor,
             oi.result_value, oi.result_unit, oi.reference_range,
             oi.status, oi.updated_at
      FROM order_items oi
      JOIN orders o ON o.id=oi.order_id
      JOIN tests t  ON t.id=oi.test_id
      JOIN patients p ON p.id=o.patient_id
      $where
      ORDER BY oi.updated_at DESC
      LIMIT 20000";
$st=$pdo->prepare($sql); $st->execute($params);

$out=fopen('php://output','w');
fputcsv($out, ['Item#','Order#','PatientID','Patient','TestCode','TestName','Dept','Doctor','Result','Unit','RefRange','Status','Updated']);
while($r=$st->fetch(PDO::FETCH_ASSOC)){
  fputcsv($out, $r);
}
