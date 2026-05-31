// Document viewing and interaction JavaScript

function showAssignModal() {
    document.getElementById('assignModal').style.display = 'block';
}

function showReturnModal() {
    if (confirm('Are you sure you want to return this document to Records Officer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'return_to_records';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function closeDocument() {
    if (confirm('This will close the document and prevent further comments. Are you sure?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'close';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function reopenDocument() {
    if (confirm('Reopen this document for further comments?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reopen';
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking on <span> (x)
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.onclick = function() {
        this.closest('.modal').style.display = 'none';
    }
});

// Close modal when clicking outside of it
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Real-time folder view - show newest document first
function loadFolderContents(folderId) {
    fetch(`/filing_system/modules/folders/get_contents.php?folder_id=${folderId}&order=DESC`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('folder-contents');
            if (data.documents && data.documents.length > 0) {
                container.innerHTML = data.documents.map(doc => `
                    <div class="document-item">
                        <span class="folio-number">Folio ${doc.folio_number}</span>
                        <a href="/filing_system/modules/documents/view.php?id=${doc.id}">
                            ${escapeHtml(doc.title)}
                        </a>
                        <span class="status ${doc.status}">${doc.status}</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p>No documents in this folder.</p>';
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-refresh comments every 30 seconds
if (window.location.pathname.includes('view.php')) {
    setInterval(() => {
        const documentId = new URLSearchParams(window.location.search).get('id');
        fetch(`/filing_system/modules/documents/get_comments.php?document_id=${documentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.comments) {
                    const commentsContainer = document.querySelector('.comments-list');
                    if (commentsContainer) {
                        commentsContainer.innerHTML = data.comments.map(comment => `
                            <div class="comment">
                                <div class="comment-header">
                                    <strong>${escapeHtml(comment.full_name)}</strong>
                                    <span class="comment-role">(${comment.role})</span>
                                    <span class="comment-date">${comment.created_at}</span>
                                </div>
                                <div class="comment-body">
                                    ${escapeHtml(comment.comment_text).replace(/\n/g, '<br>')}
                                </div>
                            </div>
                        `).join('');
                    }
                }
            });
    }, 30000);
}