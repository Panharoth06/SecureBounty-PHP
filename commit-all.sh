#!/bin/bash
# Script to commit each file individually for maximum contributions

cd /Users/macbook/Code/php/SecureBounty

# Function to generate commit message based on file path
get_message() {
    local file="$1"
    case "$file" in
        .gitignore) echo "chore: add .gitignore for project hygiene" ;;
        ProjectScope.md) echo "docs: add project scope documentation" ;;
        UIDesignSystem.md) echo "docs: add UI design system documentation" ;;
        composer.json) echo "chore: add composer.json with project dependencies" ;;
        composer.lock) echo "chore: add composer.lock for dependency pinning" ;;
        index.php) echo "feat: add application entry point" ;;
        phpunit.xml) echo "test: add PHPUnit configuration" ;;
        run.sh) echo "chore: add run script for local development" ;;
        model/repository/BaseRepository.php) echo "feat: add base repository with common database operations" ;;
        model/repository/UserRepository.php) echo "feat: add user repository for user data access" ;;
        model/repository/ProgramRepository.php) echo "feat: add program repository for bounty programs" ;;
        model/repository/ReportRepository.php) echo "feat: add report repository for vulnerability reports" ;;
        model/repository/CommentRepository.php) echo "feat: add comment repository for report discussions" ;;
        model/repository/AttachmentRepository.php) echo "feat: add attachment repository for file uploads" ;;
        model/repository/NotificationRepository.php) echo "feat: add notification repository for user alerts" ;;
        model/repository/ActivityLogRepository.php) echo "feat: add activity log repository for audit trail" ;;
        model/repository/RewardPolicyRepository.php) echo "feat: add reward policy repository" ;;
        model/repository/SavedProgramRepository.php) echo "feat: add saved program repository for bookmarks" ;;
        model/repository/UserProgramRepository.php) echo "feat: add user-program relationship repository" ;;
        model/repository/ProgramCommentRepository.php) echo "feat: add program comment repository" ;;
        model/services/AuthService.php) echo "feat: add authentication service with login and registration" ;;
        model/services/ValidationService.php) echo "feat: add validation service for input sanitization" ;;
        model/services/ProgramService.php) echo "feat: add program service for bounty program logic" ;;
        model/services/ReportService.php) echo "feat: add report service for vulnerability report handling" ;;
        model/services/CommentService.php) echo "feat: add comment service for discussion management" ;;
        model/services/AttachmentService.php) echo "feat: add attachment service for secure file handling" ;;
        model/services/NotificationService.php) echo "feat: add notification service for user alerts" ;;
        model/services/ActivityLogService.php) echo "feat: add activity log service for audit tracking" ;;
        model/services/CvssCalculatorService.php) echo "feat: add CVSS calculator service for severity scoring" ;;
        model/services/RewardPolicyService.php) echo "feat: add reward policy service" ;;
        tests/bootstrap.php) echo "test: add test bootstrap configuration" ;;
        tests/TestDatabaseHelper.php) echo "test: add test database helper utilities" ;;
        tests/controller/AdminControllerTest.php) echo "test: add admin controller tests" ;;
        tests/controller/UserControllerTest.php) echo "test: add user controller tests" ;;
        tests/middleware/AuthMiddlewareTest.php) echo "test: add authentication middleware tests" ;;
        tests/middleware/RoleMiddlewareTest.php) echo "test: add role-based middleware tests" ;;
        tests/repository/ActivityLogRepositoryTest.php) echo "test: add activity log repository tests" ;;
        tests/repository/AttachmentRepositoryTest.php) echo "test: add attachment repository tests" ;;
        tests/repository/ProgramRepositoryTest.php) echo "test: add program repository tests" ;;
        tests/repository/ReportRepositoryTest.php) echo "test: add report repository tests" ;;
        tests/services/AttachmentServiceTest.php) echo "test: add attachment service tests" ;;
        tests/services/CommentServiceTest.php) echo "test: add comment service tests" ;;
        tests/services/CvssCalculatorServiceTest.php) echo "test: add CVSS calculator service tests" ;;
        tests/services/IntegrationFlowTest.php) echo "test: add integration flow tests" ;;
        tests/services/ProgramServiceTest.php) echo "test: add program service tests" ;;
        tests/services/ReportServiceTest.php) echo "test: add report service tests" ;;
        tests/integration/ActivityLogSideEffectTest.php) echo "test: add activity log side effect integration tests" ;;
        tests/integration/AdminFlowTest.php) echo "test: add admin flow integration tests" ;;
        tests/integration/EndToEndFlowTest.php) echo "test: add end-to-end flow integration tests" ;;
        tests/property/run.php) echo "test: add property-based test runner" ;;
        view/home.php) echo "feat: add homepage view" ;;
        view/login.php) echo "feat: add login page view" ;;
        view/register.php) echo "feat: add registration page view" ;;
        view/profile.php) echo "feat: add user profile view" ;;
        view/about.php) echo "feat: add about page view" ;;
        view/contact.php) echo "feat: add contact page view" ;;
        view/docs.php) echo "feat: add documentation page view" ;;
        view/notifications.php) echo "feat: add notifications page view" ;;
        view/components/header.php) echo "feat: add header component" ;;
        view/components/footer.php) echo "feat: add footer component" ;;
        view/components/sidebar.php) echo "feat: add sidebar navigation component" ;;
        view/components/topbar.php) echo "feat: add topbar component" ;;
        view/components/layout.php) echo "feat: add layout wrapper component" ;;
        view/components/layout_end.php) echo "feat: add layout end component" ;;
        view/components/badge.php) echo "feat: add badge UI component" ;;
        view/components/toast.php) echo "feat: add toast notification component" ;;
        view/components/pagination.php) echo "feat: add pagination component" ;;
        view/components/stat-card.php) echo "feat: add statistics card component" ;;
        view/components/empty-state.php) echo "feat: add empty state component" ;;
        view/components/form-errors.php) echo "feat: add form error display component" ;;
        view/components/notifications-dropdown.php) echo "feat: add notifications dropdown component" ;;
        view/assets/style.css) echo "style: add application stylesheet" ;;
        view/assets/cvss-calculator.js) echo "feat: add CVSS calculator JavaScript" ;;
        view/programs/list.php) echo "feat: add programs listing view" ;;
        view/programs/detail.php) echo "feat: add program detail view" ;;
        view/programs/create.php) echo "feat: add program creation form view" ;;
        view/programs/edit.php) echo "feat: add program edit form view" ;;
        view/programs/saved.php) echo "feat: add saved programs view" ;;
        view/programs/reward-policy-form.php) echo "feat: add reward policy form view" ;;
        view/reports/list.php) echo "feat: add reports listing view" ;;
        view/reports/detail.php) echo "feat: add report detail view" ;;
        view/reports/submit.php) echo "feat: add report submission form view" ;;
        view/reports/edit.php) echo "feat: add report edit form view" ;;
        view/reports/comments.php) echo "feat: add report comments view" ;;
        view/dashboard/admin.php) echo "feat: add admin dashboard view" ;;
        view/dashboard/program-owner.php) echo "feat: add program owner dashboard view" ;;
        view/dashboard/researcher.php) echo "feat: add researcher dashboard view" ;;
        view/admin/users.php) echo "feat: add admin users management view" ;;
        view/admin/programs.php) echo "feat: add admin programs management view" ;;
        view/admin/activity-logs.php) echo "feat: add admin activity logs view" ;;
        uploads/attachments/.gitkeep) echo "chore: add uploads directory placeholder" ;;
        uploads/attachments/3/*) echo "chore: add sample attachment file" ;;
        tests/*/.gitkeep) echo "chore: add test directory placeholder" ;;
        .kiro/*) echo "chore: add Kiro configuration" ;;
        *) echo "feat: add $file" ;;
    esac
}

# List of files to commit in logical order
FILES=(
    ".gitignore"
    "ProjectScope.md"
    "UIDesignSystem.md"
    "composer.json"
    "composer.lock"
    "phpunit.xml"
    "run.sh"
    "index.php"
    "model/repository/BaseRepository.php"
    "model/repository/UserRepository.php"
    "model/repository/ProgramRepository.php"
    "model/repository/ReportRepository.php"
    "model/repository/CommentRepository.php"
    "model/repository/AttachmentRepository.php"
    "model/repository/NotificationRepository.php"
    "model/repository/ActivityLogRepository.php"
    "model/repository/RewardPolicyRepository.php"
    "model/repository/SavedProgramRepository.php"
    "model/repository/UserProgramRepository.php"
    "model/repository/ProgramCommentRepository.php"
    "model/services/ValidationService.php"
    "model/services/AuthService.php"
    "model/services/ProgramService.php"
    "model/services/ReportService.php"
    "model/services/CommentService.php"
    "model/services/AttachmentService.php"
    "model/services/NotificationService.php"
    "model/services/ActivityLogService.php"
    "model/services/CvssCalculatorService.php"
    "model/services/RewardPolicyService.php"
    "view/assets/style.css"
    "view/assets/cvss-calculator.js"
    "view/components/layout.php"
    "view/components/layout_end.php"
    "view/components/header.php"
    "view/components/footer.php"
    "view/components/sidebar.php"
    "view/components/topbar.php"
    "view/components/badge.php"
    "view/components/toast.php"
    "view/components/pagination.php"
    "view/components/stat-card.php"
    "view/components/empty-state.php"
    "view/components/form-errors.php"
    "view/components/notifications-dropdown.php"
    "view/home.php"
    "view/login.php"
    "view/register.php"
    "view/profile.php"
    "view/about.php"
    "view/contact.php"
    "view/docs.php"
    "view/notifications.php"
    "view/programs/list.php"
    "view/programs/detail.php"
    "view/programs/create.php"
    "view/programs/edit.php"
    "view/programs/saved.php"
    "view/programs/reward-policy-form.php"
    "view/reports/list.php"
    "view/reports/detail.php"
    "view/reports/submit.php"
    "view/reports/edit.php"
    "view/reports/comments.php"
    "view/dashboard/admin.php"
    "view/dashboard/program-owner.php"
    "view/dashboard/researcher.php"
    "view/admin/users.php"
    "view/admin/programs.php"
    "view/admin/activity-logs.php"
    "tests/bootstrap.php"
    "tests/TestDatabaseHelper.php"
    "tests/controller/.gitkeep"
    "tests/controller/AdminControllerTest.php"
    "tests/controller/UserControllerTest.php"
    "tests/middleware/.gitkeep"
    "tests/middleware/AuthMiddlewareTest.php"
    "tests/middleware/RoleMiddlewareTest.php"
    "tests/repository/.gitkeep"
    "tests/repository/ActivityLogRepositoryTest.php"
    "tests/repository/AttachmentRepositoryTest.php"
    "tests/repository/ProgramRepositoryTest.php"
    "tests/repository/ReportRepositoryTest.php"
    "tests/services/.gitkeep"
    "tests/services/AttachmentServiceTest.php"
    "tests/services/CommentServiceTest.php"
    "tests/services/CvssCalculatorServiceTest.php"
    "tests/services/IntegrationFlowTest.php"
    "tests/services/ProgramServiceTest.php"
    "tests/services/ReportServiceTest.php"
    "tests/property/.gitkeep"
    "tests/property/run.php"
    "tests/integration/ActivityLogSideEffectTest.php"
    "tests/integration/AdminFlowTest.php"
    "tests/integration/EndToEndFlowTest.php"
    "uploads/attachments/.gitkeep"
)

echo "Starting individual commits..."
echo "================================"

count=0
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        msg=$(get_message "$file")
        git add "$file"
        git commit -m "$msg" --quiet
        count=$((count + 1))
        echo "[$count] Committed: $file"
    else
        echo "[SKIP] File not found: $file"
    fi
done

# Commit remaining .kiro files
if [ -d ".kiro" ]; then
    git add .kiro/
    git commit -m "chore: add Kiro specs and configuration" --quiet 2>/dev/null && {
        count=$((count + 1))
        echo "[$count] Committed: .kiro/ directory"
    }
fi

# Commit any remaining files
git add -A
git commit -m "chore: add remaining project files" --quiet 2>/dev/null && {
    count=$((count + 1))
    echo "[$count] Committed: remaining files"
}

echo "================================"
echo "Total commits: $count"
echo ""
echo "Pushing to origin/main..."
git push origin main

echo ""
echo "Done! All $count commits pushed to GitHub."
