/*
 | Gelembung mana yang MILIK PEMBACA di Forum Diskusi.
 |
 | Dulu perataannya membaca peran: apa pun yang berlabel 'Support' ditaruh di
 | kanan pada layar Support. Support IT dan Support BPO sama-sama menyimpan
 | label itu — disengaja, karena di mata Requester keduanya satu pihak — jadi
 | pesan dua orang menumpuk di sisi yang sama, dan percakapan dua pihak terbaca
 | seperti monolog. Yang keliru bukan labelnya, melainkan memakai label untuk
 | menjawab pertanyaan "ini tulisan siapa".
 |
 | Dipakai bersama tiga layar (Support, Approver, Requester) supaya jawabannya
 | tidak bisa berbeda di antara mereka. Sebelum diangkat ke sini, logikanya
 | hanya ada di layar Support dan dua layar lainnya diam-diam tertinggal.
 */

/**
 * @param {{authorId?: number|null, authorName?: string, authorRole?: string}} comment
 * @param {{id?: number|null, name?: string}|null} viewer  identitas pembaca
 * @param {string} ownRole  label peran pembaca pada baris komentar
 * @returns {boolean}
 */
export function isMine(comment, viewer, ownRole) {
    // Tanpa identitas pembaca tidak ada yang bisa disimpulkan selain perannya —
    // perilaku lama, dan itu memang yang paling benar yang bisa dilakukan.
    if (!viewer) {
        return comment.authorRole === ownRole;
    }

    if (comment.authorId != null && viewer.id != null) {
        return comment.authorId === viewer.id;
    }

    /*
     | Komentar lama tidak menyimpan id penulis. Nama dipakai sebagai cadangan,
     | dibatasi pada peran yang sama supaya kemiripan nama antar peran tidak
     | ikut tertarik.
     |
     | Nama bukan pengganti yang layak untuk komentar baru: direktori pegawai
     | perusahaan ini memuat nama yang benar-benar kembar. Cadangan ini hanya
     | menanggung baris lama, yang jumlahnya tetap dan tidak bertambah.
    */
    return comment.authorRole === ownRole && comment.authorName === viewer.name;
}
