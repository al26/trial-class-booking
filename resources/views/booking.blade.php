<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ottodot - Trial Class Booking Reliability Engine</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN for instant rendering -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef6ff',
                            100: '#d9ebff',
                            500: '#1d68ff',
                            600: '#0c4de6',
                            700: '#083bb8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-500 flex items-center justify-center text-white font-black text-xl shadow-md shadow-brand-500/30">
                    O
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight">Ottodot Trial Booking</h1>
                    <p class="text-xs text-slate-500">Concurrency & Reliability Demo</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Engine Online
                </span>
                <span class="hidden md:inline-flex px-2.5 py-1 text-xs font-mono text-slate-500 bg-slate-100 rounded-md border border-slate-200">
                    Cap: 4 Students / Class
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Result Notification Alert -->
        @if (session('booking_result'))
            @php
                $res = session('booking_result');
                $isConfirmed = $res['status'] === 'CONFIRMED';
                $isFailed = $res['status'] === 'PAYMENT_FAILED';
                $isFull = $res['status'] === 'CLASS_FULL';
            @endphp
            <div class="p-4 rounded-2xl border transition shadow-sm {{ $isConfirmed ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : ($isFull ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-rose-50 border-rose-300 text-rose-900') }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg {{ $isConfirmed ? 'bg-emerald-600 text-white' : ($isFull ? 'bg-amber-500 text-white' : 'bg-rose-600 text-white') }}">
                            @if ($isConfirmed) ✓ @elseif ($isFull) ⚠ @else ✕ @endif
                        </div>
                        <div>
                            <h3 class="font-bold text-base">
                                @if ($isConfirmed)
                                    Booking Confirmed! Student added to Roster.
                                @elseif ($isFull)
                                    Class Full! Payment Refunded.
                                @else
                                    Payment Failed! Not added to roster.
                                @endif
                            </h3>
                            <p class="text-sm opacity-90">
                                <strong>{{ $res['student_name'] }}</strong> for class <strong>{{ $res['class_name'] }}</strong>.
                                Status: <span class="font-mono font-semibold">{{ $res['status'] }}</span>
                                @if ($res['reference_id'])
                                    | Ref: <span class="font-mono text-xs">{{ $res['reference_id'] }}</span>
                                @endif
                                @if ($isFull)
                                    (Payment attempt automatically set to <span class="underline font-semibold">REFUNDED</span>)
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 shadow-sm">
                <div class="flex items-center gap-3 font-semibold text-sm">
                    <span class="text-rose-600 text-lg font-bold">✕</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        <!-- Quick Testing Scenarios Bar -->
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white p-5 rounded-2xl shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold tracking-wider uppercase bg-brand-500 text-white">Edge-Case Simulation Guide</span>
                <h2 class="text-base font-bold mt-1 text-slate-100">Ready Scenarios to Test & Verify</h2>
                <p class="text-xs text-slate-300 mt-0.5">Test duplicate prevention, payment failure isolation, or the last-seat race condition.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" onclick="presetDuplicate()" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 border border-slate-600 font-medium transition">
                    Test Duplicate Booking (Kenzi)
                </button>
                <button type="button" onclick="presetFailure()" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 border border-slate-600 font-medium transition">
                    Test Payment Failure (Kira)
                </button>
                <div class="px-3 py-1.5 rounded-lg bg-brand-600 font-mono text-[11px] font-semibold text-white flex items-center gap-1.5">
                    CLI: <code class="bg-brand-700/60 px-1 py-0.5 rounded">php artisan test:last-seat-race</code>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT COLUMN: Parent Booking Form Simulator (5 cols) -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-brand-600 flex items-center justify-center font-bold">1</div>
                    <div>
                        <h2 class="font-bold text-slate-900 text-base">Parent Booking Simulator</h2>
                        <p class="text-xs text-slate-500">Pick a parent, student, and trial class</p>
                    </div>
                </div>

                <form action="{{ route('booking.store') }}" method="POST" id="bookingForm" class="space-y-4">
                    @csrf

                    <!-- Parent Selector -->
                    <div>
                        <label for="parent_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Select Parent
                        </label>
                        <select name="parent_id" id="parent_id" required onchange="filterStudents()"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Choose Parent --</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }} ({{ $parent->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Student Selector (Filtered dynamically) -->
                    <div>
                        <label for="student_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Select Child / Student
                        </label>
                        <select name="student_id" id="student_id" required
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Select Parent First --</option>
                        </select>
                    </div>

                    <!-- Trial Class Selector -->
                    <div>
                        <label for="trial_class_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Select Trial Class
                        </label>
                        <select name="trial_class_id" id="trial_class_id" required
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Choose Trial Class --</option>
                            @foreach ($trialClasses as $class)
                                <option value="{{ $class->id }}" {{ old('trial_class_id') == $class->id ? 'selected' : '' }}>
                                    #{{ $class->id }} {{ $class->subject }} | {{ $class->schedule_time }}
                                    ({{ $class->remainingSeats() > 0 ? $class->remainingSeats().' seat(s) left' : 'FULL' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Payment Method Selector -->
                    <div>
                        <label for="payment_method" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Payment Method
                        </label>
                        <select name="payment_method" id="payment_method" required
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="credit_card">Credit Card ($25.00)</option>
                            <option value="bank_transfer">Bank Transfer ($25.00)</option>
                            <option value="e_wallet">E-Wallet ($25.00)</option>
                        </select>
                    </div>

                    <!-- Simulate Payment Failure Toggle -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="simulate_failure" id="simulate_failure" value="1" {{ old('simulate_failure') ? 'checked' : '' }}
                            class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        <label for="simulate_failure" class="text-xs text-slate-600 cursor-pointer select-none">
                            <strong class="text-slate-900 block font-semibold">Simulate Payment Failure</strong>
                            Triggers a mock gateway decline. The child must NOT be confirmed or added to the roster.
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        class="w-full py-3 px-4 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl shadow-md shadow-brand-500/25 transition duration-150 flex items-center justify-center gap-2">
                        <span>Submit Trial Booking</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>

            <!-- RIGHT COLUMN: Teacher / Admin Live Roster Dashboard (7 cols) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">2</div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-base">Teacher / Admin Live Roster View</h2>
                            <p class="text-xs text-slate-500">Showing classes & strictly confirmed students</p>
                        </div>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-slate-200/70 text-slate-700 rounded-lg">
                        {{ $trialClasses->count() }} Classes
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    @foreach ($trialClasses as $class)
                        @php
                            $confirmedCount = $class->confirmedBookingsCount();
                            $remaining = $class->remainingSeats();
                            $isFull = $class->isFull();
                            $confirmedBookings = $class->bookings->where('status', \App\Enums\BookingStatus::Confirmed);
                        @endphp
                        <div class="bg-white rounded-2xl border {{ $isFull ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }} p-5 shadow-sm space-y-3.5">
                            
                            <!-- Class Header & Capacity Badge -->
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold">ID #{{ $class->id }}</span>
                                        <h3 class="font-bold text-slate-900 text-base">{{ $class->subject }}</h3>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        👨‍🏫 {{ $class->teacher_name }} &bull; 🗓 {{ $class->schedule_date?->format('M d, Y') }} &bull; ⏰ {{ $class->schedule_time }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold {{ $isFull ? 'bg-rose-100 text-rose-800' : ($remaining === 1 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $confirmedCount }} / {{ $class->max_capacity }} Seats
                                    </span>
                                    <p class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                        @if ($isFull)
                                            CLASS FULL
                                        @elseif ($remaining === 1)
                                            ⚡ LAST SEAT AVAILABLE
                                        @else
                                            {{ $remaining }} Available
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <!-- Visual Capacity Progress Bar -->
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300 {{ $isFull ? 'bg-rose-500' : ($remaining === 1 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                    style="width: {{ ($confirmedCount / $class->max_capacity) * 100 }}%"></div>
                            </div>

                            <!-- Confirmed Students Roster Section -->
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                                    Confirmed Roster ({{ $confirmedBookings->count() }})
                                </h4>

                                @if ($confirmedBookings->isEmpty())
                                    <p class="text-xs text-slate-400 italic py-2">No students confirmed yet. Class is empty.</p>
                                @else
                                    <ul class="divide-y divide-slate-100 text-xs">
                                        @foreach ($confirmedBookings as $b)
                                            <li class="py-1.5 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-[10px]">
                                                        {{ $loop->iteration }}
                                                    </span>
                                                    <div>
                                                        <strong class="text-slate-800 font-semibold">{{ $b->student->name }}</strong>
                                                        <span class="text-slate-400">({{ $b->student->age }} y/o)</span>
                                                        <span class="text-slate-400 text-[11px]">&bull; Parent: {{ $b->parent->name }}</span>
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    CONFIRMED
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </main>

    <!-- Client-side Data Map & Quick Fill Script -->
    <script>
        const parentsData = @json($parents);

        function filterStudents(selectedStudentId = null) {
            const parentId = document.getElementById('parent_id').value;
            const studentSelect = document.getElementById('student_id');
            studentSelect.innerHTML = '<option value="">-- Choose Student --</option>';

            if (!parentId) return;

            const parent = parentsData.find(p => p.id == parentId);
            if (parent && parent.students) {
                parent.students.forEach(s => {
                    const option = document.createElement('option');
                    option.value = s.id;
                    option.textContent = `${s.name} (${s.age} y/o)`;
                    if (selectedStudentId && s.id == selectedStudentId) {
                        option.selected = true;
                    }
                    studentSelect.appendChild(option);
                });
            }
        }

        // Quick Scenarios Fillers
        function presetDuplicate() {
            // Kenzi (already confirmed in Class A: Roblox Science Explorer)
            const budi = parentsData.find(p => p.email === 'budi@example.com');
            if (budi) {
                document.getElementById('parent_id').value = budi.id;
                const kenzi = budi.students.find(s => s.name === 'Kenzi');
                filterStudents(kenzi ? kenzi.id : null);
                document.getElementById('trial_class_id').value = 1; // Class 1
                document.getElementById('simulate_failure').checked = false;
            }
        }

        function presetFailure() {
            // Kira with simulate failure checked
            const budi = parentsData.find(p => p.email === 'budi@example.com');
            if (budi) {
                document.getElementById('parent_id').value = budi.id;
                const kira = budi.students.find(s => s.name === 'Kira');
                filterStudents(kira ? kira.id : null);
                document.getElementById('trial_class_id').value = 4; // Class 4
                document.getElementById('simulate_failure').checked = true;
            }
        }

        // Run filter on initial load if old value exists
        window.addEventListener('DOMContentLoaded', () => {
            const oldParentId = "{{ old('parent_id') }}";
            const oldStudentId = "{{ old('student_id') }}";
            if (oldParentId) {
                filterStudents(oldStudentId);
            }
        });
    </script>
</body>
</html>
