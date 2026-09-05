<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_verify(); $action=$_POST['action']??'';
    if($action==='add'){
        $name=trim($_POST['name']??''); $high=!empty($_POST['is_high_value_default']);
        if($name==='') $errors[]='Category name is required.';
        else { try{$pdo->prepare('INSERT INTO categories(name,is_high_value_default) VALUES(?,?)')->execute([$name,$high?1:0]); admin_log($pdo,'category_add','category',(int)$pdo->lastInsertId(),'Added category “'.$name.'”.'); flash_set('Category added.','success'); redirect('/admin/manage_categories.php');}catch(PDOException $e){$errors[]='A category with that name already exists.';} }
    } elseif($action==='delete'){
        $id=(int)($_POST['category_id']??0); $stmt=$pdo->prepare('SELECT name,(SELECT COUNT(*) FROM posts WHERE category_id=categories.id) post_count FROM categories WHERE id=?'); $stmt->execute([$id]); $cat=$stmt->fetch();
        if(!$cat) flash_set('Category not found.','error');
        elseif((int)$cat['post_count']>0) flash_set('Cannot delete a category that still has reports.','error');
        else { $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); admin_log($pdo,'category_delete','category',$id,'Deleted category “'.$cat['name'].'”.'); flash_set('Category deleted.','success'); }
        redirect('/admin/manage_categories.php');
    }
}
$categories=$pdo->query('SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id=c.id) post_count FROM categories c ORDER BY c.name')->fetchAll();
$pageTitle='Categories'; $adminActive='posts'; include __DIR__.'/../includes/admin_header.php';
?>
<a class="admin-back-link" href="<?= BASE_URL ?>/admin/posts.php">← Back to posts</a>
<section class="admin-section-intro"><div><h2>Report categories</h2><p>Categories shape feed filters and can flag certain report types as high-value by default.</p></div></section>
<?php if($errors): ?><div class="flash flash-error"><ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="admin-category-grid">
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">Current</span><h2>Categories</h2></div></div><div class="admin-category-list"><?php foreach($categories as $cat): ?><div class="admin-category-row"><span><strong><?= e($cat['name']) ?></strong><small><?= (int)$cat['post_count'] ?> reports<?= $cat['is_high_value_default']?' · High-value by default':'' ?></small></span><?php if((int)$cat['post_count']===0): ?><form method="post" onsubmit="return confirm('Delete this category?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>"><button class="admin-icon-button danger" type="submit" aria-label="Delete category">×</button></form><?php else: ?><span class="admin-soft-pill">In use</span><?php endif; ?></div><?php endforeach; ?></div></section>
<section class="admin-card"><div class="admin-card-header"><div><span class="admin-card-kicker">New</span><h2>Add category</h2></div></div><form method="post" class="admin-form"><?= csrf_field() ?><input type="hidden" name="action" value="add"><label>Category name<input type="text" name="name" required></label><label class="checkbox-label"><input type="checkbox" name="is_high_value_default" value="1"> High-value by default</label><button class="btn btn-primary" type="submit">Add category</button></form></section>
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
