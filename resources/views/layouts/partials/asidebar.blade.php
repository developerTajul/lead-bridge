<!-- Admin Sidebar -->
<aside class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 bg-slate-900 text-white">
        <div class="flex items-center justify-center h-20 shadow-md bg-slate-950">
            <h1 class="text-2xl font-bold tracking-wider"><i class="fa-solid fa-gauge-high mr-2 text-indigo-400"></i>AdminPanel</h1>
        </div>
            @if (Route::has('admin.users.index'))
                @php
                    $activeMenuKey = request()->routeIs('admin.users.*') ? 'users'
                        : (request()->routeIs('admin.taxonomies.*') ? 'taxonomies'
                        : (request()->routeIs('admin.tags.*') ? 'tags'
                        : (request()->routeIs('admin.exam-sessions.*') ? 'exam-sessions'
                        : (request()->routeIs('admin.questions.*') ? 'questions' : ''))));
                @endphp
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto"
             x-data="{ openMenu: '{{ $activeMenuKey }}' }">

            <!-- Users Dropdown Menu -->
            <div>
                <button type="button"
                    @click="openMenu = (openMenu === 'users' ? null : 'users')"
                    :aria-expanded="openMenu === 'users' ? 'true' : 'false'"
                    :class="openMenu === 'users' ? 'text-white bg-indigo-600' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg shadow-sm focus:outline-none transition-colors">
                    <span class="flex items-center"><i class="fa-solid fa-user-gear w-6"></i> Users</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300 ease-in-out" :class="openMenu === 'users' ? 'rotate-180 text-white' : 'rotate-0 text-slate-400'"></i>
                </button>
                <div x-show="openMenu === 'users'"
                     x-transition:enter="transition-all ease-out duration-300"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition-all ease-in duration-200"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     {{ $activeMenuKey !== 'users' ? 'style="display: none;"' : '' }}
                     class="overflow-hidden pl-4 pr-2 py-2 space-y-1 bg-slate-950/50 rounded-lg mt-1">
                    <a href="{{ route('admin.users.index') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.users.index') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.users.index') ? 'text-indigo-400' : '' }}"></i> User List
                    </a>
                    <a href="{{ route('admin.users.create') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.users.create') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.users.create') ? 'text-indigo-400' : '' }}"></i> Add User
                    </a>
                </div>
            </div>

            <!-- Taxonomies Dropdown Menu -->
            <div>
                <button type="button"
                    @click="openMenu = (openMenu === 'taxonomies' ? null : 'taxonomies')"
                    :aria-expanded="openMenu === 'taxonomies' ? 'true' : 'false'"
                    :class="openMenu === 'taxonomies' ? 'text-white bg-indigo-600' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg shadow-sm focus:outline-none transition-colors">
                    <span class="flex items-center"><i class="fa-solid fa-tags w-6"></i> Taxonomies</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300 ease-in-out" :class="openMenu === 'taxonomies' ? 'rotate-180 text-white' : 'rotate-0 text-slate-400'"></i>
                </button>
                <div x-show="openMenu === 'taxonomies'"
                     x-transition:enter="transition-all ease-out duration-300"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition-all ease-in duration-200"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     {{ $activeMenuKey !== 'taxonomies' ? 'style="display: none;"' : '' }}
                     class="overflow-hidden pl-4 pr-2 py-2 space-y-1 bg-slate-950/50 rounded-lg mt-1">
                    <a href="{{ route('admin.taxonomies.create') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.taxonomies.create') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.taxonomies.create') ? 'text-indigo-400' : '' }}"></i> Add Taxonomy
                    </a>
                    <a href="{{ route('admin.taxonomies.index') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.taxonomies.index') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.taxonomies.index') ? 'text-indigo-400' : '' }}"></i> All Taxonomies
                    </a>
                </div>
            </div>

            <!-- Tags Dropdown Menu -->
            <div>
                <button type="button"
                    @click="openMenu = (openMenu === 'tags' ? null : 'tags')"
                    :aria-expanded="openMenu === 'tags' ? 'true' : 'false'"
                    :class="openMenu === 'tags' ? 'text-white bg-indigo-600' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg shadow-sm focus:outline-none transition-colors">
                    <span class="flex items-center"><i class="fa-solid fa-tag w-6"></i> Tags</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300 ease-in-out" :class="openMenu === 'tags' ? 'rotate-180 text-white' : 'rotate-0 text-slate-400'"></i>
                </button>
                <div x-show="openMenu === 'tags'"
                     x-transition:enter="transition-all ease-out duration-300"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition-all ease-in duration-200"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     {{ $activeMenuKey !== 'tags' ? 'style="display: none;"' : '' }}
                     class="overflow-hidden pl-4 pr-2 py-2 space-y-1 bg-slate-950/50 rounded-lg mt-1">
                    <a href="{{ route('admin.tags.index') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.tags.index') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.tags.index') ? 'text-indigo-400' : '' }}"></i> Tag List
                    </a>
                    <a href="{{ route('admin.tags.create') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.tags.create') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.tags.create') ? 'text-indigo-400' : '' }}"></i> Add Tag
                    </a>
                </div>
            </div>

            <!-- Exam Sessions Dropdown Menu -->
            <div>
                <button type="button"
                    @click="openMenu = (openMenu === 'exam-sessions' ? null : 'exam-sessions')"
                    :aria-expanded="openMenu === 'exam-sessions' ? 'true' : 'false'"
                    :class="openMenu === 'exam-sessions' ? 'text-white bg-indigo-600' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg shadow-sm focus:outline-none transition-colors">
                    <span class="flex items-center"><i class="fa-solid fa-calendar-days w-6"></i> Exam Sessions</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300 ease-in-out" :class="openMenu === 'exam-sessions' ? 'rotate-180 text-white' : 'rotate-0 text-slate-400'"></i>
                </button>
                <div x-show="openMenu === 'exam-sessions'"
                     x-transition:enter="transition-all ease-out duration-300"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition-all ease-in duration-200"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     {{ $activeMenuKey !== 'exam-sessions' ? 'style="display: none;"' : '' }}
                     class="overflow-hidden pl-4 pr-2 py-2 space-y-1 bg-slate-950/50 rounded-lg mt-1">
                    <a href="{{ route('admin.exam-sessions.index') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.exam-sessions.index') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.exam-sessions.index') ? 'text-indigo-400' : '' }}"></i> Exam Session List
                    </a>
                    <a href="{{ route('admin.exam-sessions.create') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.exam-sessions.create') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.exam-sessions.create') ? 'text-indigo-400' : '' }}"></i> Add Exam Session
                    </a>
                </div>
            </div>
            <!-- Questions Dropdown Menu -->
            <div>
                <button type="button"
                    @click="openMenu = (openMenu === 'questions' ? null : 'questions')"
                    :aria-expanded="openMenu === 'questions' ? 'true' : 'false'"
                    :class="openMenu === 'questions' ? 'text-white bg-indigo-600' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg shadow-sm focus:outline-none transition-colors">
                    <span class="flex items-center"><i class="fa-solid fa-circle-question w-6"></i> Questions</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300 ease-in-out" :class="openMenu === 'questions' ? 'rotate-180 text-white' : 'rotate-0 text-slate-400'"></i>
                </button>
                <div x-show="openMenu === 'questions'"
                     x-transition:enter="transition-all ease-out duration-300"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-96"
                     x-transition:leave="transition-all ease-in duration-200"
                     x-transition:leave-start="opacity-100 max-h-96"
                     x-transition:leave-end="opacity-0 max-h-0"
                     {{ $activeMenuKey !== 'questions' ? 'style="display: none;"' : '' }}
                     class="overflow-hidden pl-4 pr-2 py-2 space-y-1 bg-slate-950/50 rounded-lg mt-1">
                    <a href="{{ route('admin.questions.index') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.questions.index') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.questions.index') ? 'text-indigo-400' : '' }}"></i> Question List
                    </a>
                    <a href="{{ route('admin.questions.create') }}"
                       class="flex items-center px-4 py-2 rounded-md {{ request()->routeIs('admin.questions.create') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                        <i class="fa-solid fa-circle-dot w-4 text-[10px] mr-2 {{ request()->routeIs('admin.questions.create') ? 'text-indigo-400' : '' }}"></i> Add Question
                    </a>
                </div>
            </div>
        </nav>
        @endif
        <div class="p-4 bg-slate-950">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center w-full px-4 py-2 text-red-400 hover:bg-slate-900 rounded-lg"><i class="fa-solid fa-right-from-bracket w-6"></i> Logout</button>
            </form>
        </div>
    </div>
</aside>
