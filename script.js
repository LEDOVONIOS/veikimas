// Sample project data for demonstration
const sampleProjects = [
    {
        id: 1,
        name: "Example Project",
        url: "https://example.com",
        status: "operational",
        incidents: 0,
        createdDate: "Jul 31, 2025"
    }
];

// Status configurations
const statusConfig = {
    operational: {
        text: "Operational",
        className: "status-operational"
    },
    degraded: {
        text: "Degraded",
        className: "status-degraded"
    },
    down: {
        text: "Down",
        className: "status-down"
    }
};

// Function to create a project card element
function createProjectCard(project) {
    const card = document.createElement('div');
    card.className = 'project-card';
    card.setAttribute('data-project-id', project.id);
    
    const statusInfo = statusConfig[project.status] || statusConfig.operational;
    
    card.innerHTML = `
        <div class="card-header">
            <h2 class="project-name">${escapeHtml(project.name)}</h2>
            <span class="status-badge ${statusInfo.className}">${statusInfo.text}</span>
        </div>
        
        <a href="${escapeHtml(project.url)}" class="project-url" target="_blank">${escapeHtml(project.url)}</a>
        
        <div class="divider"></div>
        
        <div class="metadata">
            <span class="incident-count">Total Incidents: ${project.incidents}</span>
            <span class="created-date">Created: ${escapeHtml(project.createdDate)}</span>
        </div>
    `;
    
    return card;
}

// Function to escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to render all project cards
function renderProjects(projects) {
    const grid = document.querySelector('.projects-grid');
    grid.innerHTML = ''; // Clear existing cards
    
    projects.forEach(project => {
        const card = createProjectCard(project);
        grid.appendChild(card);
    });
}

// Function to add a new project (example implementation)
function addNewProject() {
    // This is a demonstration of how to add a new project
    // In a real application, this would open a form or modal
    const newProject = {
        id: Date.now(), // Simple ID generation
        name: "New Project " + (sampleProjects.length + 1),
        url: "https://newproject.com",
        status: "operational",
        incidents: 0,
        createdDate: new Date().toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        })
    };
    
    sampleProjects.push(newProject);
    renderProjects(sampleProjects);
}

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', () => {
    // Render initial projects
    renderProjects(sampleProjects);
    
    // Add event listener to the Add New Project button
    const addButton = document.querySelector('.add-project-btn');
    addButton.addEventListener('click', addNewProject);
});

// Example function to update project status dynamically
function updateProjectStatus(projectId, newStatus) {
    const project = sampleProjects.find(p => p.id === projectId);
    if (project && statusConfig[newStatus]) {
        project.status = newStatus;
        renderProjects(sampleProjects);
    }
}

// Example function to update incident count
function updateIncidentCount(projectId, incidentCount) {
    const project = sampleProjects.find(p => p.id === projectId);
    if (project) {
        project.incidents = incidentCount;
        renderProjects(sampleProjects);
    }
}

// Export functions for external use
window.dashboardAPI = {
    addProject: (project) => {
        sampleProjects.push({
            id: project.id || Date.now(),
            name: project.name,
            url: project.url,
            status: project.status || 'operational',
            incidents: project.incidents || 0,
            createdDate: project.createdDate || new Date().toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            })
        });
        renderProjects(sampleProjects);
    },
    updateStatus: updateProjectStatus,
    updateIncidents: updateIncidentCount,
    getProjects: () => [...sampleProjects],
    removeProject: (projectId) => {
        const index = sampleProjects.findIndex(p => p.id === projectId);
        if (index > -1) {
            sampleProjects.splice(index, 1);
            renderProjects(sampleProjects);
        }
    }
};