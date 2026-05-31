<?php
require_once '../../config/session.php';
requireLogin();
require_once '../../includes/functions.php';

$page_title = 'Search Documents';
$base_url = '../../';

$conn = getConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

$search_query = $_GET['q'] ?? '';
$search_type = $_GET['type'] ?? 'all';
$results = [];

if (!empty($search_query)) {
    $search_term = "%$search_query%";
    
    if ($search_type == 'documents' || $search_type == 'all') {
        $doc_query = "SELECT d.*, f.name as folder_name, u.full_name as submitted_by_name,
                      MATCH(d.title, d.content) AGAINST(? IN BOOLEAN MODE) as relevance
                      FROM documents d
                      JOIN folders f ON d.folder_id = f.id
                      JOIN users u ON d.submitted_by = u.id
                      WHERE (d.title LIKE ? OR d.content LIKE ? OR d.document_number LIKE ?)";
        
        // Apply role-based filtering
        if ($user_role === 'user') {
            $doc_query .= " AND d.submitted_by = ?";
            $stmt = $conn->prepare($doc_query);
            $stmt->bind_param("ssssi", $search_term, $search_term, $search_term, $search_term, $user_id);
        } elseif ($user_role === 'records_officer') {
            $doc_query .= " AND d.status NOT IN ('submitted_to_admin', 'in_review')";
            $stmt = $conn->prepare($doc_query);
            $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
        } else {
            $stmt = $conn->prepare($doc_query);
            $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
        }
        
        $stmt->execute();
        $results['documents'] = $stmt->get_result();
    }
    
    if ($search_type == 'folders' || $search_type == 'all') {
        $folder_query = "SELECT f.*, c.name as category_name,
                         COUNT(d.id) as document_count
                         FROM folders f
                         LEFT JOIN categories c ON f.category_id = c.id
                         LEFT JOIN documents d ON f.id = d.folder_id
                         WHERE f.name LIKE ? OR f.folder_number LIKE ? OR f.description LIKE ?
                         GROUP BY f.id";
        $stmt = $conn->prepare($folder_query);
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $results['folders'] = $stmt->get_result();
    }
}

include_once '../../includes/header.php';
include_once '../../includes/sidebar.php';
?>

<style>
    .search-container {
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .search-box {
        margin-bottom: 30px;
    }
    
    .search-input-group {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .search-input-group input {
        flex: 1;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 16px;
    }
    
    .search-input-group input:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .search-type {
        display: flex;
        gap: 15px;
        padding: 10px 0;
    }
    
    .search-type label {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }
    
    .btn-search {
        padding: 12px 30px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .btn-search:hover {
        background: #5a67d8;
    }
    
    .results-section {
        margin-top: 30px;
    }
    
    .result-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        transition: background 0.3s;
    }
    
    .result-item:hover {
        background: #f9f9f9;
    }
    
    .result-title {
        font-size: 18px;
        margin-bottom: 8px;
    }
    
    .result-title a {
        color: #667eea;
        text-decoration: none;
    }
    
    .result-title a:hover {
        text-decoration: underline;
    }
    
    .result-meta {
        font-size: 13px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .result-excerpt {
        font-size: 14px;
        color: #555;
        margin-top: 5px;
    }
    
    .highlight {
        background: #fff3cd;
        padding: 0 2px;
    }
    
    .no-results {
        text-align: center;
        padding: 50px;
        color: #666;
    }
    
    .search-stats {
        background: #f0f4f8;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
        .search-input-group {
            flex-direction: column;
        }
        
        .search-type {
            flex-wrap: wrap;
        }
    }
</style>

<div class="content-wrapper">
    <h2>Search Documents & Folders</h2>
    
    <div class="search-container">
        <form method="GET" action="">
            <div class="search-box">
                <div class="search-input-group">
                    <input type="text" name="q" placeholder="Search by title, content, document number..." 
                           value="<?php echo htmlspecialchars($search_query); ?>" required>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                
                <div class="search-type">
                    <label>
                        <input type="radio" name="type" value="all" <?php echo $search_type == 'all' ? 'checked' : ''; ?>>
                        All (Documents & Folders)
                    </label>
                    <label>
                        <input type="radio" name="type" value="documents" <?php echo $search_type == 'documents' ? 'checked' : ''; ?>>
                        Documents Only
                    </label>
                    <label>
                        <input type="radio" name="type" value="folders" <?php echo $search_type == 'folders' ? 'checked' : ''; ?>>
                        Folders Only
                    </label>
                </div>
            </div>
        </form>
        
        <?php if (!empty($search_query)): ?>
            <div class="search-stats">
                <i class="fas fa-chart-line"></i> 
                Search results for: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
            </div>
            
            <div class="results-section">
                <?php if (isset($results['documents']) && $results['documents']->num_rows > 0): ?>
                    <h3>Documents (<?php echo $results['documents']->num_rows; ?>)</h3>
                    <?php while ($doc = $results['documents']->fetch_assoc()): ?>
                        <div class="result-item">
                            <div class="result-title">
                                <a href="../documents/view.php?id=<?php echo $doc['id']; ?>">
                                    <?php echo highlightText($doc['title'], $search_query); ?>
                                </a>
                            </div>
                            <div class="result-meta">
                                <span><i class="fas fa-hashtag"></i> <?php echo $doc['document_number']; ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($doc['submitted_by_name']); ?></span>
                            </div>
                            <?php if ($doc['content']): ?>
                                <div class="result-excerpt">
                                    <?php echo highlightText(substr(strip_tags($doc['content']), 0, 200), $search_query) . '...'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                
                <?php if (isset($results['folders']) && $results['folders']->num_rows > 0): ?>
                    <h3 style="margin-top: 30px;">Folders (<?php echo $results['folders']->num_rows; ?>)</h3>
                    <?php while ($folder = $results['folders']->fetch_assoc()): ?>
                        <div class="result-item">
                            <div class="result-title">
                                <a href="../folders/view.php?id=<?php echo $folder['id']; ?>">
                                    <i class="fas fa-folder"></i> <?php echo highlightText($folder['name'], $search_query); ?>
                                </a>
                            </div>
                            <div class="result-meta">
                                <span><i class="fas fa-hashtag"></i> <?php echo $folder['folder_number']; ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($folder['category_name']); ?></span>
                                <span style="margin-left: 15px;"><i class="fas fa-file"></i> <?php echo $folder['document_count']; ?> documents</span>
                            </div>
                            <?php if ($folder['description']): ?>
                                <div class="result-excerpt">
                                    <?php echo highlightText(substr($folder['description'], 0, 200), $search_query) . '...'; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                
                <?php if ((!isset($results['documents']) || $results['documents']->num_rows == 0) && 
                          (!isset($results['folders']) || $results['folders']->num_rows == 0)): ?>
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 48px; color: #ccc;"></i>
                        <p>No results found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                        <p style="font-size: 14px;">Try different keywords or browse folders instead.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search" style="font-size: 64px; color: #ddd;"></i>
                <h3>Search for documents and folders</h3>
                <p>Enter keywords to search through document titles, content, and folder names.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
function highlightText($text, $search) {
    if (empty($search) || empty($text)) {
        return htmlspecialchars($text);
    }
    $escaped_text = htmlspecialchars($text);
    $escaped_search = htmlspecialchars($search);
    return preg_replace("/($escaped_search)/i", "<span class='highlight'>$1</span>", $escaped_text);
}

include_once '../../includes/footer.php';
?>