<?php
/**
 * Profile Modals Component
 * 
 * Required variables:
 * - $profilePic: Path to profile picture
 * - $student: Array with firstname, lastname, email, gender, student_id, section
 * - $studentName: Student's full name
 */
?>
<!-- Profile Picture Upload Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content-small">
        <div class="flex items-center justify-between mb-4 lg:mb-6">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Update Profile Picture</h2>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2">
                <i class="fas fa-times text-lg lg:text-xl"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <div class="mb-4 lg:mb-6">
                <div class="flex justify-center mb-4">
                    <div class="relative">
                        <img id="previewImage" src="<?= htmlspecialchars($profilePic) ?>" alt="Preview" class="w-24 h-24 lg:w-32 lg:h-32 rounded-full object-cover ring-4 ring-primary-500">
                    </div>
                </div>
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Choose New Picture</label>
                <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" 
                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-primary-500"
                       onchange="previewProfilePicture(event)">
                <p class="mt-2 text-xs text-gray-500">Supported formats: JPG, PNG, GIF (Max 5MB)</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2 lg:gap-3">
                <button type="submit" class="flex-1 bg-primary-600 text-white px-4 lg:px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors text-sm lg:text-base">
                    <i class="fas fa-upload mr-2"></i>
                    Upload Picture
                </button>
                <button type="button" onclick="closeUploadModal()" class="flex-1 bg-gray-200 text-gray-700 px-4 lg:px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors text-sm lg:text-base">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Profile Settings Modal -->
<div id="profileSettingsModal" class="modal">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="gradient-bg px-4 lg:px-6 py-4 lg:py-5 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xl lg:text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-circle mr-2 lg:mr-3"></i>
                    <span class="hidden sm:inline">Profile Settings</span>
                    <span class="sm:hidden">Settings</span>
                </h2>
                <button onclick="closeProfileSettingsModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-colors">
                    <i class="fas fa-times text-lg lg:text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 px-4 lg:px-6 overflow-x-auto">
            <button class="tab-button active flex-shrink-0" onclick="switchTab(event, 'account')">
                <i class="fas fa-user mr-1 lg:mr-2"></i>
                <span class="hidden sm:inline">Account Details</span>
                <span class="sm:hidden">Account</span>
            </button>
            <button class="tab-button flex-shrink-0" onclick="switchTab(event, 'password')">
                <i class="fas fa-lock mr-1 lg:mr-2"></i>
                <span class="hidden sm:inline">Change Password</span>
                <span class="sm:hidden">Password</span>
            </button>
        </div>

        <!-- Tab Contents -->
        <div class="p-4 lg:p-6">
            <!-- Account Details Tab -->
            <div id="account-tab" class="tab-content active">
                <div class="space-y-4 lg:space-y-6">
                    <!-- Profile Picture Section -->
                    <div class="text-center pb-4 lg:pb-6 border-b border-gray-200">
                        <div class="relative inline-block">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-24 h-24 lg:w-32 lg:h-32 rounded-full object-cover ring-4 ring-primary-500 mx-auto mb-3 lg:mb-4">
                            <button type="button" onclick="openUploadModal()" class="absolute bottom-3 lg:bottom-4 right-0 bg-primary-600 hover:bg-primary-700 text-white rounded-full p-2 lg:p-3 shadow-lg transition-colors">
                                <i class="fas fa-camera text-sm"></i>
                            </button>
                        </div>
                        <h3 class="text-lg lg:text-xl font-semibold text-gray-900 mt-2"><?= htmlspecialchars(ucwords(strtolower($studentName))) ?></h3>
                        <p class="text-sm lg:text-base text-gray-600">Student Account</p>
                    </div>

                    <!-- Account Information -->
                    <div class="space-y-3 lg:space-y-4">
                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-primary-500 mr-2"></i>First Name
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['firstname']) ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>

                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-primary-500 mr-2"></i>Last Name
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['lastname']) ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>

                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-primary-500 mr-2"></i>Email Address
                            </label>
                            <input type="email" value="<?= htmlspecialchars($student['email']) ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>

                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-venus-mars text-primary-500 mr-2"></i>Gender
                            </label>
                            <input type="text" value="<?= htmlspecialchars(ucfirst($student['gender'] ?? 'Not specified')) ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>

                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-id-card text-primary-500 mr-2"></i>Student ID
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['student_id'] ?? 'Not assigned') ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>

                        <div>
                            <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-users text-primary-500 mr-2"></i>Section
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['section'] ?? 'Not assigned') ?>" readonly class="w-full px-3 lg:px-4 py-2 lg:py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 cursor-not-allowed text-sm lg:text-base">
                        </div>
                    </div>

                    <div class="bg-blue-50 border-l-4 border-primary-500 p-3 lg:p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-info-circle text-primary-500 mt-0.5 mr-2 lg:mr-3 flex-shrink-0"></i>
                            <div>
                                <p class="text-xs lg:text-sm font-semibold text-primary-900 mb-1">Account Information</p>
                                <p class="text-xs lg:text-sm text-primary-700">To update your account details, please contact your administrator.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content">
                <form id="changePasswordForm" class="space-y-4 lg:space-y-6">
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 lg:p-4 rounded-r-lg mb-4 lg:mb-6">
                        <div class="flex">
                            <i class="fas fa-shield-alt text-yellow-500 mt-0.5 mr-2 lg:mr-3 flex-shrink-0"></i>
                            <div>
                                <p class="text-xs lg:text-sm font-semibold text-yellow-900 mb-1">Password Security</p>
                                <p class="text-xs lg:text-sm text-yellow-700">Choose a strong password with at least 8 characters, including uppercase, lowercase, numbers, and symbols.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-primary-500 mr-2"></i>Current Password
                        </label>
                        <div class="relative">
                            <input type="password" id="currentPassword" name="currentPassword" required 
                                   class="w-full px-3 lg:px-4 py-2 lg:py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm lg:text-base"
                                   placeholder="Enter your current password">
                            <button type="button" onclick="togglePasswordVisibility('currentPassword')" 
                                    class="absolute right-2 lg:right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 p-2">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-key text-primary-500 mr-2"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" id="newPassword" name="newPassword" required 
                                   class="w-full px-3 lg:px-4 py-2 lg:py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm lg:text-base"
                                   placeholder="Enter your new password">
                            <button type="button" onclick="togglePasswordVisibility('newPassword')" 
                                    class="absolute right-2 lg:right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 p-2">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                        <div id="passwordStrength" class="mt-2 hidden">
                            <div class="flex items-center space-x-2">
                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="strengthBar" class="h-full transition-all duration-300"></div>
                                </div>
                                <span id="strengthText" class="text-xs lg:text-sm font-medium"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs lg:text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-check-circle text-primary-500 mr-2"></i>Confirm New Password
                        </label>
                        <div class="relative">
                            <input type="password" id="confirmPassword" name="confirmPassword" required 
                                   class="w-full px-3 lg:px-4 py-2 lg:py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm lg:text-base"
                                   placeholder="Confirm your new password">
                            <button type="button" onclick="togglePasswordVisibility('confirmPassword')" 
                                    class="absolute right-2 lg:right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 p-2">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                        <p id="passwordMatch" class="mt-2 text-xs lg:text-sm hidden"></p>
                    </div>

                    <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-3 lg:p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2 lg:mr-3 flex-shrink-0"></i>
                            <p class="text-xs lg:text-sm text-red-700" id="passwordErrorText"></p>
                        </div>
                    </div>

                    <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-3 lg:p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 lg:mr-3 flex-shrink-0"></i>
                            <p class="text-xs lg:text-sm text-green-700">Password changed successfully!</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 lg:gap-3 pt-4">
                        <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 lg:px-6 py-3 rounded-lg font-semibold transition-colors shadow-sm text-sm lg:text-base">
                            <i class="fas fa-save mr-2"></i>Update Password
                        </button>
                        <button type="button" onclick="resetPasswordForm()" class="px-4 lg:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors text-sm lg:text-base">
                            <i class="fas fa-undo mr-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>