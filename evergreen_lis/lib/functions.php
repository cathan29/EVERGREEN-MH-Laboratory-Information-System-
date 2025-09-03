<?php
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money($n){ return '₱'.number_format((float)$n, 2); }

function status_badge($s){
  switch($s){
    case 'completed':          return '<span class="badge success">completed</span>';
    case 'in_processing':      return '<span class="badge info">in processing</span>';
    case 'pending_collection': return '<span class="badge">pending</span>';
    default:                   return '<span class="badge">'.h($s).'</span>';
  }
}

function abnormal_chip($flag){
  return ((int)$flag === 1) ? '<span class="badge danger" title="Abnormal">abnormal</span>' : '';
}

/** Format: "Last, First M. Suffix" (any missing parts are skipped cleanly) */
function format_name(string $last=null, string $first=null, ?string $middle=null, ?string $suffix=null): string {
  $last  = trim((string)$last);
  $first = trim((string)$first);
  $middle = trim((string)($middle ?? ''));
  $suffix = trim((string)($suffix ?? ''));

  $mi = $middle !== '' ? (' '.mb_substr($middle, 0, 1).'.') : '';
  $suf = $suffix !== '' ? (' '.$suffix) : '';

  if ($last === '' && $first === '') return '';
  return trim($last.($last!=='' && $first!=='' ? ', ' : '').$first.$mi.$suf);
}

/** Helper for rows that include name columns */
function format_name_row(array $row): string {
  return format_name($row['last_name'] ?? '', $row['first_name'] ?? '', $row['middle_name'] ?? null, $row['suffix'] ?? null);
}

function counts(PDO $pdo){
  $testsToday = $pdo->query("SELECT COUNT(*) FROM order_items WHERE DATE(updated_at)=CURDATE()")->fetchColumn();
  $pending    = $pdo->query("SELECT COUNT(*) FROM order_items WHERE status!='completed'")->fetchColumn();
  $completed  = $pdo->query("SELECT COUNT(*) FROM order_items WHERE status='completed'")->fetchColumn();
  $rev = $pdo->query("
     SELECT COALESCE(SUM(t.price),0)
     FROM order_items oi
     JOIN tests t ON t.id=oi.test_id
     WHERE oi.status='completed' AND DATE(oi.completed_at)=CURDATE()
  ")->fetchColumn();
  return ['testsToday'=>$testsToday,'pending'=>$pending,'completed'=>$completed,'revenueToday'=>$rev];
}

/* -------------------------- Searching helpers -------------------------- */
function build_where(array $filters, array &$params){
  $where = [];

  if(!empty($filters['q'])){
    $whereTxt = "("
      ."p.first_name LIKE :q OR p.middle_name LIKE :q OR p.last_name LIKE :q OR p.suffix LIKE :q "
      ."OR t.name LIKE :q OR t.code LIKE :q OR t.department LIKE :q "
      ."OR o.ordering_doctor LIKE :q"
      .")";
    $params[':q'] = '%'.$filters['q'].'%';

    $digits = preg_replace('/\D+/', '', $filters['q']);
    if($digits !== ''){
      $whereId = "(oi.id = :qId OR o.id = :qId OR p.id = :qId)";
      $params[':qId'] = (int)$digits;
      $where[] = "($whereTxt OR $whereId)";
    } else {
      $where[] = $whereTxt;
    }
  }
  if(!empty($filters['date_from'])){
    $where[] = "DATE(oi.updated_at) >= :from";
    $params[':from'] = $filters['date_from'];
  }
  if(!empty($filters['date_to'])){
    $where[] = "DATE(oi.updated_at) <= :to";
    $params[':to'] = $filters['date_to'];
  }
  if(!empty($filters['status'])){
    $where[] = "oi.status = :st";
    $params[':st'] = $filters['status'];
  }
  if(!empty($filters['patient_id'])){
    $where[] = "o.patient_id = :pid";
    $params[':pid'] = (int)$filters['patient_id'];
  }
  return $where ? (' WHERE '.implode(' AND ', $where)) : '';
}

/* -------------------------- Abnormality helpers -------------------------- */
function parse_range($ref){
  if(!$ref) return [null,null];
  if(preg_match('/(-?\d+(\.\d+)?)\s*-\s*(-?\d+(\.\d+)?)/', $ref, $m)){
    return [floatval($m[1]), floatval($m[3])];
  }
  return [null,null];
}

function detect_abnormal($value, $refRange){
  if(!preg_match('/-?\d+(\.\d+)?/', (string)$value, $m)) return 0;
  $v = floatval($m[0]);
  [$low,$high] = parse_range($refRange);
  if($low===null || $high===null) return 0;
  return ($v < $low || $v > $high) ? 1 : 0;
}

function detect_abnormal_text($test_code, $value){
  $v = trim((string)$value);
  $map = [
    'UA-PRO'=>['Negative'=>0,'Trace'=>1,'1+'=>2,'2+'=>3,'3+'=>4,'4+'=>5],
    'UA-GLU'=>['Negative'=>0,'Trace'=>1,'1+'=>2,'2+'=>3,'3+'=>4],
    'UA-KET'=>['Negative'=>0,'Trace'=>1,'1+'=>2,'2+'=>3,'3+'=>4],
    'UA-BLD'=>['Negative'=>0,'Trace'=>1,'1+'=>2,'2+'=>3,'3+'=>4],
    'UA-LEU'=>['Negative'=>0,'Trace'=>1,'1+'=>2,'2+'=>3,'3+'=>4],
    'UA-NIT'=>['Negative'=>0,'Positive'=>2],
    'UA-BIL'=>['Negative'=>0,'Positive'=>2],
  ];
  if(isset($map[$test_code]) && isset($map[$test_code][$v])){
    return $map[$test_code][$v] > 0 ? 1 : 0;
  }
  return 0;
}
