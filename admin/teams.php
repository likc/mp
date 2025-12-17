<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se é admin (usando is_admin)
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Gerenciar Times';
$message = '';
$messageType = '';

// Criar pasta de upload se não existir
$uploadDir = __DIR__ . '/../uploads/teams/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_team'])) {
            $name = sanitize($_POST['name']);
            $slug = sanitize($_POST['slug']);
            $country = sanitize($_POST['country']);
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            
            $logoPath = '';
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $fileName = time() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $logoPath = 'uploads/teams/' . $fileName;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO teams (name, slug, logo, country, display_order, active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $logoPath, $country, $displayOrder, $active]);
            
            $message = 'Time adicionado com sucesso!';
            $messageType = 'success';
            
        } elseif (isset($_POST['edit_team'])) {
            $id = intval($_POST['id']);
            $name = sanitize($_POST['name']);
            $slug = sanitize($_POST['slug']);
            $country = sanitize($_POST['country']);
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("SELECT logo FROM teams WHERE id = ?");
            $stmt->execute([$id]);
            $currentTeam = $stmt->fetch();
            $logoPath = $currentTeam['logo'];
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                if (!empty($logoPath) && file_exists(__DIR__ . '/../' . $logoPath)) {
                    unlink(__DIR__ . '/../' . $logoPath);
                }
                
                $fileName = time() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $logoPath = 'uploads/teams/' . $fileName;
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE teams 
                SET name = ?, slug = ?, logo = ?, country = ?, display_order = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $logoPath, $country, $displayOrder, $active, $id]);
            
            $message = 'Time atualizado com sucesso!';
            $messageType = 'success';
            
        } elseif (isset($_POST['delete_team'])) {
            $id = intval($_POST['id']);
            
            $stmt = $pdo->prepare("SELECT logo FROM teams WHERE id = ?");
            $stmt->execute([$id]);
            $team = $stmt->fetch();
            
            if ($team && !empty($team['logo']) && file_exists(__DIR__ . '/../' . $team['logo'])) {
                unlink(__DIR__ . '/../' . $team['logo']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'Time deletado com sucesso!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar todos os times
$teams = $pdo->query("SELECT * FROM teams ORDER BY display_order, name")->fetchAll();

// Contar produtos por time (se existir)
$teamStats = [];
try {
    $stmt = $pdo->query("SELECT team_id, COUNT(*) as total FROM products WHERE team_id IS NOT NULL GROUP BY team_id");
    while ($row = $stmt->fetch()) {
        $teamStats[$row['team_id']] = $row['total'];
    }
} catch (Exception $e) {
    // Coluna team_id não existe
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #2d7a4a; font-size: 32px; }
        .back-btn { background: #666; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size: 48px; font-weight: bold; color: #2d7a4a; }
        .stat-label { color: #666; margin-top: 5px; }
        .add-button { background: #2d7a4a; color: white; padding: 14px 32px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-bottom: 20px; }
        .teams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .team-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: relative; }
        .team-logo { width: 100%; height: 160px; object-fit: contain; margin-bottom: 15px; background: #f5f5f5; border-radius: 8px; padding: 15px; }
        .team-name { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
        .team-country { color: #666; font-size: 14px; margin-bottom: 12px; }
        .team-stats { display: flex; gap: 10px; margin-bottom: 15px; font-size: 13px; }
        .team-stat { background: #f5f5f5; padding: 6px 12px; border-radius: 5px; flex: 1; text-align: center; }
        .team-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn { padding: 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-delete { background: #dc3545; color: white; }
        .inactive { opacity: 0.5; }
        .inactive-badge { position: absolute; top: 15px; right: 15px; background: #666; color: white; padding: 6px 12px; border-radius: 5px; font-size: 12px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; padding: 20px; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 35px; border-radius: 12px; max-width: 550px; width: 100%; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 26px; font-weight: bold; margin-bottom: 25px; color: #2d7a4a; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 15px; }
        .form-group input[type="file"] { border: none; padding: 8px 0; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .form-actions { display: flex; gap: 12px; margin-top: 25px; }
        .btn-primary { flex: 1; background: #2d7a4a; color: white; padding: 14px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-secondary { flex: 1; background: #666; color: white; padding: 14px; border: none; border-radius: 6px; cursor: pointer; }
        .logo-preview { max-width: 250px; max-height: 250px; margin-top: 15px; border: 2px solid #ddd; border-radius: 8px; padding: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>⚽ Gerenciar Times</h1>
                <p>Adicione logos e nomes dos times</p>
            </div>
            <a href="dashboard.php" class="back-btn">← Voltar</a>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teams); ?></div>
                <div class="stat-label">Total de Times</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($teams, fn($t) => $t['active'])); ?></div>
                <div class="stat-label">Times Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($teams, fn($t) => !empty($t['logo']))); ?></div>
                <div class="stat-label">Com Logo</div>
            </div>
        </div>
        
        <button class="add-button" onclick="openAddModal()">➕ Adicionar Novo Time</button>
        
        <div class="teams-grid">
            <?php foreach ($teams as $team): ?>
            <div class="team-card <?php echo !$team['active'] ? 'inactive' : ''; ?>">
                <?php if (!$team['active']): ?>
                    <div class="inactive-badge">Inativo</div>
                <?php endif; ?>
                
                <?php if (!empty($team['logo'])): ?>
                    <img src="../<?php echo htmlspecialchars($team['logo']); ?>" 
                         alt="<?php echo htmlspecialchars($team['name']); ?>"
                         class="team-logo">
                <?php else: ?>
                    <div class="team-logo" style="display: flex; align-items: center; justify-content: center; font-size: 64px;">⚽</div>
                <?php endif; ?>
                
                <div class="team-name"><?php echo htmlspecialchars($team['name']); ?></div>
                <div class="team-country">📍 <?php echo htmlspecialchars($team['country'] ?? 'N/A'); ?></div>
                
                <div class="team-stats">
                    <span class="team-stat">Ordem: #<?php echo $team['display_order']; ?></span>
                    <span class="team-stat">Produtos: <?php echo $teamStats[$team['id']] ?? 0; ?></span>
                </div>
                
                <div class="team-actions">
                    <button class="btn btn-edit" onclick='openEditModal(<?php echo json_encode($team); ?>)'>✏️ Editar</button>
                    <button class="btn btn-delete" onclick="deleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')">🗑️ Deletar</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Modal Adicionar -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">➕ Adicionar Novo Time</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nome do Time *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL) *</label>
                    <input type="text" name="slug" required placeholder="ex: real-madrid">
                </div>
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="country" placeholder="ex: Espanha">
                </div>
                <div class="form-group">
                    <label>Logo</label>
                    <input type="file" name="logo" accept="image/*" onchange="previewImage(this, 'addPreview')">
                    <img id="addPreview" class="logo-preview" style="display: none;">
                </div>
                <div class="form-group">
                    <label>Ordem de Exibição</label>
                    <input type="number" name="display_order" value="0">
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="active" id="add_active" checked>
                    <label for="add_active">Ativo</label>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_team" class="btn-primary">Adicionar</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Editar Time</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nome do Time *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL) *</label>
                    <input type="text" name="slug" id="edit_slug" required>
                </div>
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="country" id="edit_country">
                </div>
                <div class="form-group">
                    <label>Logo</label>
                    <img id="current_logo" class="logo-preview" style="display: none;">
                    <input type="file" name="logo" accept="image/*" onchange="previewImage(this, 'editPreview')">
                    <img id="editPreview" class="logo-preview" style="display: none;">
                </div>
                <div class="form-group">
                    <label>Ordem de Exibição</label>
                    <input type="number" name="display_order" id="edit_display_order">
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="active" id="edit_active">
                    <label for="edit_active">Ativo</label>
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_team" class="btn-primary">Salvar</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="id" id="delete_id">
        <input type="hidden" name="delete_team" value="1">
    </form>
    
    <script>
    function openAddModal() { document.getElementById('addModal').classList.add('show'); }
    function openEditModal(team) {
        document.getElementById('edit_id').value = team.id;
        document.getElementById('edit_name').value = team.name;
        document.getElementById('edit_slug').value = team.slug;
        document.getElementById('edit_country').value = team.country || '';
        document.getElementById('edit_display_order').value = team.display_order;
        document.getElementById('edit_active').checked = team.active == 1;
        const currentLogo = document.getElementById('current_logo');
        if (team.logo) {
            currentLogo.src = '../' + team.logo;
            currentLogo.style.display = 'block';
        } else {
            currentLogo.style.display = 'none';
        }
        document.getElementById('editPreview').style.display = 'none';
        document.getElementById('editModal').classList.add('show');
    }
    function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
    function deleteTeam(id, name) {
        if (confirm('Deseja deletar o time "' + name + '"?')) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }
    </script>
</body>
</html>