<?php
declare(strict_types = 1);                                // Use strict types
require 'includes/database-connection.php';               // Create PDO object
require 'includes/functions.php';                         // Include functions
require 'includes/va1idate.php';

$uploads = dirname(_DIR_ , 1) . DIRECTORY _SEPARATOR . 'uploads ' . DIRECTORY_SEPARATOR;
$file_types = [  'image/jpeg ' , 'image/png ' , 'image/gif ' , ];
$file_exts = ['jpg' , 'jpeg' , 'png' , 'gif' , ] ; 
$max_size = 5242880 ;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$temp=$_FILES['image']['tmp_name']??'';
$description='';                                          // Validate id

$article=[

'id'                 =>$id, 'title'     =>'',
'summary'            =>'', 'content'    =>'',
'member_id'          =>0, 'category_id' =>0,
'image_id'           =>null, 'pubilshed'=>false,
'image_file'         =>'', 'image_alt'  =>'',
  
];

$errors = [
    'warning'  => '',
    'title'    => '',
    'summary'  => '',
    'content'  => '',
    'category' => '',
    'author'   => '',
    'image'    => ''
];


if (!$id) {                                               // If no valid id
    include 'page-not-found.php';                         // Page not found
}

$sql = "SELECT a.title, a.summary, a.content, a.created, a.category_id, a.member_id, 
               c.name      AS category,
               CONCAT(m.forename, ' ', m.surname) AS author,
               i.file AS image_file,
               i.alt  AS image_alt 
          FROM article     AS a
          JOIN category    AS c  ON a.category_id = c.id
          JOIN member      AS m  ON a.member_id   = m.id
          LEFT JOIN image  AS i  ON a.image_id    = i.id
         WHERE a.id = :id  AND a.published = 1;";         // SQL statement

$article = $article = pdo($pdo, $sql, [$id])->fetch();    // Get article data
if (!$article) {                                          // If article not found
    include 'page-not-found.php';                         // Page not found
}

$saved_image = $artic1e[ ’image_file’]? true : false ;

$sql = "SELECT id, name FROM category WHERE navigation = 1;"; // SQL to get categories
$navigation  = pdo($pdo, $sql)->fetchAll();               // Get navigation categories
$section     = $article['category_id'];                   // Current category
$title       = $article['title'];                         // HTML <title> content
$description = $article['summary'];                       // Meta description content
?>
<?php include 'includes/header.php'; ?>
  <main class="article container" id="content">
    <section class="image">
      <img src="uploads/<?= html_escape($article['image_file'] ?? 'blank.png') ?>" 
           alt="<?= html_escape($article['image_alt']) ?>">
    </section>
    <section class="text">
      <h1><?= html_escape($article['title']) ?></h1>
      <div class="date"><?= format_date($article['created']) ?></div>
      <div class="content"><?= html_escape($article['content']) ?></div>
      <p class="credit">
        Posted in <a href="category.php?id=<?= $article['category_id'] ?>"><?= html_escape($article['category']) ?></a> by <a href="member.php?id=<?= $article['member_id'] ?>">
          <?= html_escape($article['author']) ?></a>
      </p>
    </section>
  </main>
<?php include 'includes/footer.php';

$temp =$_FILES['image']['tmp_name'] ?? '';
$destination='';

// 파트 B
// Part B : 폼 데이터 가져오고 유효성 검사하기
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 파일이 php.ini 또는 .htaccess에서 제한된 크기보다 큰 경우
    $errors['image_file'] =
        ($_FILES['image']['error'] === 1)
        ? 'File too big'
        : '';

    // 이미지가 업로드된 경우
    if ($temp and $_FILES['image']['error'] === 0) {

        // 대체 텍스트 가져오기
        $article['image_alt'] = $_POST['image_alt'];

        // 파일 타입 검사
        $errors['image_file'] .=
            in_array(mime_content_type($temp), $file_types)
            ? ''
            : 'Wrong file type. ';

        // 확장자 검사
        $ext = strtolower(
            pathinfo(
                $_FILES['image']['name'],
                PATHINFO_EXTENSION
            )
        );

        $errors['image_file'] .=
            in_array($ext, $file_exts)
            ? ''
            : 'Wrong file extension. ';

        // 파일 크기 검사
        $errors['image_file'] .=
            ($_FILES['image']['size'] <= $max_size)
            ? ''
            : 'File too big. ';

        // Alt 텍스트 검사
        $errors['image_alt'] =
            is_text($article['image_alt'], 1, 254)
            ? ''
            : 'Alt text must be 1-254 characters.';

        // 이미지가 유효하면 저장 위치 지정
        if (
            $errors['image_file'] === '' &&
            $errors['image_alt'] === ''
        ) {

            $article['image_file'] =
                create_filename(
                    $_FILES['image']['name'],
                    $uploads
                );

            $destination =
                $uploads . $article['image_file'];
        }
    }

    // 기사 데이터 가져오기
    $article['title']       = $_POST['title'];
    $article['summary']     = $_POST['summary'];
    $article['content']     = $_POST['content'];
    $article['member_id']   = $_POST['member_id'];
    $article['category_id'] = $_POST['category_id'];

    $article['published'] =
        (isset($_POST['published'])
        and ($_POST['published'] == 1))
        ? 1
        : 0;

    // 유효성 검사
    $errors['title'] =
        is_text($article['title'], 1, 80)
        ? ''
        : 'Title must be 1-80 characters';

    $errors['summary'] =
        is_text($article['summary'], 1, 254)
        ? ''
        : 'Summary must be 1-254 characters';

    $errors['content'] =
        is_text($article['content'], 1, 100000)
        ? ''
        : 'Article must be 1-100000 characters';

    $errors['member'] =
        is_member_id(
            $article['member_id'],
            $authors
        )
        ? ''
        : 'Please select an author';

    $errors['category'] =
        is_category_id(
            $article['category_id'],
            $categories
        )
        ? ''
        : 'Please select a category';

    // 오류 결합
    $invalid = implode($errors);
}

// 파트 C: 데이터가 유효한지 확인하고, 유효하다면 데이터베이스 업데이트하기
if ($invalid) {
    // 유효하지 않다면
    $errors['warning'] = 'Please correct the errors below'; // 메시지 저장
} else {
    // 그렇지 않다면
    $arguments = $article; // 기사 데이터 저장
    try {
        // 데이터 삽입 시도
        $pdo->beginTransaction(); // 트랜잭션 시작
        if ($destination) {
            // 유효한 이미지가 있다면
            $imagick = new \Imagick($temp); // Imagick 객체 생성
            $imagick->cropThumbnailImage(1200, 700); // 자른 이미지 생성
            $imagick->writeImage($destination); // 파일 저장
            $sql = "INSERT INTO image (file, alt) 
                    VALUES (:file, :alt);"; // 이미지를 추가하는 SQL
            pdo($pdo, $sql, ['image_file' => $arguments['image_file'], 'image_alt' => $arguments['image_alt']]);
            $arguments['image_id'] = $pdo->lastInsertId(); // 새로운 이미지 아이디 얻기
        }

        unset($arguments['image_file'], $arguments['image_alt']); // 이미지 데이터 잘라내기
        if ($id) {
            $sql = "UPDATE article 
                    SET title = :title, summary = :summary, content = :content, 
                        category_id = :category_id, member_id = :member_id, 
                        image_id = :image_id, published = :published 
                    WHERE id = :id;"; // 기사 업데이트하는 SQL
        } else {
            unset($arguments['id']); // 아이디 제거
            $sql = "INSERT INTO article (title, summary, content, category_id, 
                                         member_id, image_id, published) 
                    VALUES (:title, :summary, :content, :category_id, :member_id, 
                            :image_id, :published);"; // 기사를 생성하는 SQL
        }

        pdo($pdo, $sql, $arguments); // 기사를 추가하는 SQL 실행
        $pdo->commit(); // 변경사항 커밋
        redirect('articles.php', ['success' => 'Article saved']); // 리디렉션
    } catch (PDOException $e) { // PDOException이 발생했다면
        $pdo->rollBack(); // SQL 변경사항 롤백
        if (file_exists($destination)) { // 이미지 파일이 존재한다면
            unlink($destination); // 이미지 파일 삭제
        } // 예외가 PDOException이고 무결성 제약에 걸렸다면
        if ($e->errorInfo[1] === 1062) {
            $errors['warning'] = "Article title already used"; // 경고를 저장
        } else { // 그렇지 않다면
            throw $e; // 예외를 다시 발생
        }
    }
} // 새로운 이미지가 업로드되었지만 데이터가 유효하지 않다면, $article에서 이미지 제거

$article['image_file'] = $saved_image ? $article['image_file'] : ''; ...
?>

