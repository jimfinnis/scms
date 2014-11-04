(defun trim (x)
    (string-trim `(#\Space #\Newline) x))

(defun write-header (header)
    (format t "\\subsection{~a}~%" header))
    
(defun write-tag-title-and-description (title desc)
    (format t "\\tag{~a}{~a}{~%" title desc))

(defun get-option-flags (items)
     (apply 'logior (mapcar #'(lambda (item)
        (apply 'logior (mapcar #'(lambda (opt)
                (if (eq (car opt) item) (cadr opt) 0))
            '(
                (:optional 1) (:noproc 2)
             ))))items)))

(defun write-argument (list count)
    (if list
        (progn
            (let ((flags (get-option-flags (cdar list))))
                (format t (concatenate 'string "\\textbf{"
                    (cond   ((= flags 3) "(*~a)")
                            ((= flags 2) "*~a")
                            ((= flags 1) "(~a)")
                            (t "~a"))
                    "}: ~a"
                    (if (cdr list) "\\\\" "")
                    "~%")
                    count (caar list))
            (write-argument (cdr list) (+ count 1))))))

(with-open-file (stream "tmp.txt")
    (mapcar #'(lambda (list)
        (write-header (car list))
        (mapcar #'(lambda (list)
            (write-tag-title-and-description (car list) (cadr list))
            (write-argument (caddr list) 0)
            (format t "}~%"))
        (cdr list)))
    (read stream)))
